<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Services\Fixpulse\IssueActionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IssueActionController extends Controller
{
    public function generatePatch(Request $request, Issue $issue, IssueActionService $service): RedirectResponse
    {
        $this->authorizeIssue($request, $issue);

        try {
            $service->generatePatch($issue->loadMissing('resources'), $request->user());

            return back()->with('status', 'Patch proposal generated.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Generate Patch failed: '.$e->getMessage());
        }
    }

    public function createPr(Request $request, Issue $issue, IssueActionService $service): RedirectResponse
    {
        $this->authorizeIssue($request, $issue);

        try {
            $service->createPr($issue->loadMissing('resources'), $request->user());

            return back()->with('status', 'PR created successfully.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Create PR failed: '.$e->getMessage());
        }
    }

    public function revertPr(Request $request, Issue $issue, IssueActionService $service): RedirectResponse
    {
        $this->authorizeIssue($request, $issue);

        try {
            $service->revertPr($issue, $request->user());

            return back()->with('status', 'Revert PR created successfully.');
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Revert PR failed: '.$e->getMessage());
        }
    }

    private function authorizeIssue(Request $request, Issue $issue): void
    {
        abort_unless($issue->scan && $issue->scan->user_id === $request->user()->id, 404);
    }
}

