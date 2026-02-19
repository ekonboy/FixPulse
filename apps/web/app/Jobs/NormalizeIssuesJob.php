<?php

namespace App\Jobs;

use App\Models\Issue;
use App\Models\Scan;
use App\Models\ScanArtifact;
use App\Services\IssueNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class NormalizeIssuesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $scanId)
    {
        $this->onQueue('scans');
    }

    public function handle(IssueNormalizer $normalizer): void
    {
        $scan = Scan::query()->with('artifacts')->find($this->scanId);
        if (! $scan || $scan->status !== 'done') {
            return;
        }

        $artifact = $scan->artifacts()->where('kind', 'lhr_json')->latest()->first();
        if (! $artifact) {
            return;
        }

        $raw = Storage::disk('local')->get($artifact->path);
        $lhr = json_decode($raw, true);
        if (! is_array($lhr)) {
            return;
        }

        $normalizedIssues = $normalizer->normalize($lhr);
        $normalizedPath = sprintf('scans/%d/normalized.json', $scan->id);
        Storage::disk('local')->put($normalizedPath, json_encode($normalizedIssues, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        ScanArtifact::query()->updateOrCreate(
            ['scan_id' => $scan->id, 'kind' => 'normalized_json'],
            [
                'path' => $normalizedPath,
                'meta_jsonb' => [
                    'generated_at' => now()->toIso8601String(),
                    'source' => 'issue_normalizer',
                    'issues_count' => count($normalizedIssues),
                ],
            ]
        );

        Issue::query()->where('scan_id', $scan->id)->delete();

        foreach ($normalizedIssues as $normalizedIssue) {
            $resources = $normalizedIssue['resources'];
            unset($normalizedIssue['resources']);

            $issue = $scan->issues()->create($normalizedIssue);

            foreach ($resources as $resource) {
                $issue->resources()->create($resource);
            }
        }
    }
}
