<?php

namespace App\Jobs;

use App\Models\Scan;
use App\Models\ScanArtifact;
use App\Services\RunnerClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RunLighthouseJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $scanId)
    {
        $this->onQueue('scans');
    }

    public function handle(RunnerClient $runnerClient): void
    {
        $scan = Scan::query()->with('project')->find($this->scanId);
        if (! $scan || $scan->status !== 'queued') {
            return;
        }

        $userLock = Cache::lock('scan:user:'.$scan->user_id, 120);
        $projectLock = Cache::lock('scan:project:'.$scan->project_id, 120);
        $hasUserLock = $userLock->get();
        $hasProjectLock = $projectLock->get();
        $globalLock = ($hasUserLock && $hasProjectLock) ? $this->acquireGlobalLock() : null;

        if (! $hasUserLock || ! $hasProjectLock || $globalLock === null) {
            if ($globalLock) {
                $globalLock->release();
            }
            if ($hasProjectLock) {
                $projectLock->release();
            }
            if ($hasUserLock) {
                $userLock->release();
            }
            $this->release(15);

            return;
        }

        try {
            $scan->update([
                'status' => 'running',
                'started_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

            $lhr = $runnerClient->auditLighthouse($scan->target_url, $scan->device);
            $artifactPath = sprintf('scans/%d/lhr.json', $scan->id);

            Storage::disk('local')->put($artifactPath, json_encode($lhr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            ScanArtifact::query()->updateOrCreate(
                ['scan_id' => $scan->id, 'kind' => 'lhr_json'],
                [
                    'path' => $artifactPath,
                    'meta_jsonb' => [
                        'generated_at' => now()->toIso8601String(),
                        'runner' => 'lighthouse',
                    ],
                ]
            );

            $scan->update([
                'status' => 'done',
                'summary_jsonb' => $this->extractSummary($lhr),
                'finished_at' => now(),
            ]);

            Bus::chain([
                new NormalizeIssuesJob($scan->id),
                new BuildFixPlanJob($scan->id),
            ])->onQueue('scans')->dispatch();
        } catch (\Throwable $exception) {
            Log::error('RunLighthouseJob failed', [
                'scan_id' => $scan->id,
                'message' => $exception->getMessage(),
            ]);

            $scan->update([
                'status' => 'failed',
                'error_code' => 'RUNNER_FAILURE',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);

            throw $exception;
        } finally {
            $globalLock->release();
            $projectLock->release();
            $userLock->release();
        }
    }

    private function acquireGlobalLock(): ?\Illuminate\Contracts\Cache\Lock
    {
        for ($slot = 1; $slot <= 2; $slot++) {
            $lock = Cache::lock('scan:global:'.$slot, 120);
            if ($lock->get()) {
                return $lock;
            }
        }

        return null;
    }

    private function extractSummary(array $lhr): array
    {
        $performance = (int) round((float) Arr::get($lhr, 'categories.performance.score', 0) * 100);
        $seo = (int) round((float) Arr::get($lhr, 'categories.seo.score', 0) * 100);
        $a11y = (int) round((float) Arr::get($lhr, 'categories.accessibility.score', 0) * 100);
        $bestPractices = (int) round((float) Arr::get($lhr, 'categories.best-practices.score', 0) * 100);

        // Weighted score prioritizing business value:
        // Performance 50%, SEO 25%, Accessibility 15%, Best Practices 10%.
        $weighted = (int) round(
            ($performance * 0.50)
            + ($seo * 0.25)
            + ($a11y * 0.15)
            + ($bestPractices * 0.10)
        );

        return [
            'performance_score' => $performance,
            'seo_score' => $seo,
            'a11y_score' => $a11y,
            'best_practices_score' => $bestPractices,
            'weighted_score' => $weighted,
            'weighted_formula' => [
                'performance' => 0.50,
                'seo' => 0.25,
                'accessibility' => 0.15,
                'best_practices' => 0.10,
            ],
            'fcp_ms' => (int) round((float) Arr::get($lhr, 'audits.first-contentful-paint.numericValue', 0)),
            'lcp_ms' => (int) round((float) Arr::get($lhr, 'audits.largest-contentful-paint.numericValue', 0)),
            'cls' => (float) Arr::get($lhr, 'audits.cumulative-layout-shift.numericValue', 0),
            'tbt_ms' => (int) round((float) Arr::get($lhr, 'audits.total-blocking-time.numericValue', 0)),
        ];
    }
}
