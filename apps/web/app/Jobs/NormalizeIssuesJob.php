<?php

namespace App\Jobs;

use App\Models\Issue;
use App\Models\Scan;
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
