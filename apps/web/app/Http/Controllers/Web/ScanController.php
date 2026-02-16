<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\BuildFixPlanJob;
use App\Http\Requests\StoreScanRequest;
use App\Jobs\RunLighthouseJob;
use App\Models\Project;
use App\Models\Scan;
use App\Services\TechnologyLookupService;
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
}
