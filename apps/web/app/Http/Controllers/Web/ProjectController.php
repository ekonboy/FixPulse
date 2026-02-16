<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $projects = Project::query()
            ->where('user_id', $request->user()->id)
            ->withCount('scans')
            ->latest('id')
            ->get();

        return view('projects.index', compact('projects'));
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = Project::query()->create([
            'user_id' => $request->user()->id,
            'name' => (string) $request->string('name'),
            'base_url' => (string) $request->string('base_url'),
        ]);

        return redirect()->route('projects.show', $project);
    }

    public function show(Request $request, Project $project): View
    {
        abort_unless($project->user_id === $request->user()->id, 404);

        $project->load(['scans' => fn ($query) => $query->latest('id')]);

        return view('projects.show', compact('project'));
    }
}
