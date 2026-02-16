<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScanRequest;
use App\Jobs\RunLighthouseJob;
use App\Models\Project;
use App\Models\Scan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    public function store(StoreScanRequest $request, Project $project): JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 404);

        $scan = Scan::query()->create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'target_url' => (string) $request->string('target_url'),
            'device' => (string) $request->string('device'),
            'mode' => (string) $request->string('mode', 'lighthouse'),
            'status' => 'queued',
        ]);

        RunLighthouseJob::dispatch($scan->id);

        return response()->json($scan, 201);
    }

    public function show(Request $request, Scan $scan): JsonResponse
    {
        abort_unless($scan->user_id === $request->user()->id, 404);

        $scan->load('project', 'artifacts', 'fixPlan');

        return response()->json($scan);
    }

    public function issues(Request $request, Scan $scan): JsonResponse
    {
        abort_unless($scan->user_id === $request->user()->id, 404);

        return response()->json(
            $scan->issues()->with('resources')->orderByDesc('priority_score')->get()
        );
    }

    public function plan(Request $request, Scan $scan): JsonResponse
    {
        abort_unless($scan->user_id === $request->user()->id, 404);

        return response()->json($scan->fixPlan);
    }
}
