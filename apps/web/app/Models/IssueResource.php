<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueResource extends Model
{
    protected $fillable = [
        'issue_id',
        'resource_type',
        'url',
        'transfer_size_kb',
        'details_jsonb',
    ];

    protected function casts(): array
    {
        return [
            'details_jsonb' => 'array',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
