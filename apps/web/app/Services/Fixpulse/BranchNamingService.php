<?php

namespace App\Services\Fixpulse;

use App\Models\Issue;
use Illuminate\Support\Str;

class BranchNamingService
{
    public function issueKeyToSlug(string $issueKey): string
    {
        $max = (int) config('fixpulse_actions.git.max_issue_key_length', 60);
        $slug = Str::of($issueKey)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/i', '-')
            ->trim('-')
            ->toString();

        if ($slug === '') {
            $slug = 'issue';
        }

        return Str::limit($slug, $max, '');
    }

    public function branchForIssue(Issue $issue): string
    {
        $prefix = trim((string) config('fixpulse_actions.git.branch_prefix', 'fixpulse'), '/');
        $scanId = (int) $issue->scan_id;
        $slug = $this->issueKeyToSlug((string) $issue->key);

        return sprintf('%s/%d-%s', $prefix, $scanId, $slug);
    }

    public function commitMessage(Issue $issue, ?int $part = null): string
    {
        $base = sprintf('fix(scan:%d): %s', (int) $issue->scan_id, $this->issueKeyToSlug((string) $issue->key));

        if ($part !== null && $part > 1) {
            return $base.' (part '.$part.')';
        }

        return $base;
    }
}

