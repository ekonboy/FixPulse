<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    protected $fillable = [
        'scan_id',
        'key',
        'category',
        'title',
        'severity',
        'impact_score',
        'effort_score',
        'estimated_saving_ms',
        'estimated_saving_kb',
        'evidence_jsonb',
        'fix_jsonb',
        'priority_score',
    ];

    protected function casts(): array
    {
        return [
            'evidence_jsonb' => 'array',
            'fix_jsonb' => 'array',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(IssueResource::class);
    }
}
