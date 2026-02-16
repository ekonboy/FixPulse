<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::query()
            ->where('user_id', $request->user()->id)
            ->withCount('scans')
            ->latest('id')
            ->get();

        return response()->json($projects);
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::query()->create([
            'user_id' => $request->user()->id,
            'name' => (string) $request->string('name'),
            'base_url' => (string) $request->string('base_url'),
        ]);

        return response()->json($project, 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 404);

        $project->load(['scans' => fn ($q) => $q->latest('id')]);

        return response()->json($project);
    }
}
