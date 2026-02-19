<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\BuildFixPlanJob;
use App\Http\Requests\StoreScanRequest;
use App\Jobs\RunLighthouseJob;
use App\Models\Project;
use App\Models\Scan;
use App\Services\TechnologyLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function store(StoreScanRequest $request, Project $project): RedirectResponse
    {
        abort_unless($project->user_id === $request->user()->id, 404);

        $scan = Scan::query()->create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'target_url' => (string) $request->string('target_url'),
            'device' => (string) $request->string('device'),
            'mode' => 'lighthouse',
            'status' => 'queued',
        ]);

        RunLighthouseJob::dispatch($scan->id);

        return redirect()->route('scans.show', $scan);
    }

    public function show(Request $request, Scan $scan, TechnologyLookupService $technologyLookupService): View
    {
        abort_unless($scan->user_id === $request->user()->id, 404);

        $scan->load([
            'project',
            'issues.resources',
            'issues.actions',
            'fixPlan',
        ]);

        if ($scan->status === 'done' && $scan->fixPlan) {
            $hasMissingGuidance = collect(data_get($scan->fixPlan->plan_jsonb, 'buckets', []))
                ->flatten(1)
                ->contains(fn ($item) => blank(data_get($item, 'fix_summary')));

            if ($hasMissingGuidance) {
                BuildFixPlanJob::dispatchSync($scan->id);
                $scan->load('fixPlan');
            }
        }

        $technology = Cache::remember(
            'scan:tech:'.$scan->id,
            now()->addHours(12),
            function () use ($scan, $technologyLookupService): array {
                try {
                    return $technologyLookupService->resolvePrimary($scan->target_url);
                } catch (\Throwable $exception) {
                    return [
                        'source' => 'error',
                        'primary' => null,
                        'summary' => 'No se pudo detectar la tecnología: '.$exception->getMessage(),
                        'technologies' => [],
                    ];
                }
            }
        );

        return view('scans.show', compact('scan', 'technology'));
    }

    public function status(Request $request, Scan $scan): JsonResponse
    {
        abort_unless($scan->user_id === $request->user()->id, 404);

        $scan->loadCount('issues');

        return response()->json([
            'id' => $scan->id,
            'status' => $scan->status,
            'issues_count' => $scan->issues_count,
            'error_code' => $scan->error_code,
            'error_message' => $scan->error_message,
            'started_at' => optional($scan->started_at)?->toIso8601String(),
            'finished_at' => optional($scan->finished_at)?->toIso8601String(),
            'updated_at' => optional($scan->updated_at)?->toIso8601String(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
