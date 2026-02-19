<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueAction extends Model
{
    protected $fillable = [
        'issue_id',
        'user_id',
        'kind',
        'status',
        'branch_name',
        'commit_message',
        'commit_sha',
        'pr_url',
        'pr_number',
        'patch_jsonb',
        'diff_text',
        'meta_jsonb',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'patch_jsonb' => 'array',
            'meta_jsonb' => 'array',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

