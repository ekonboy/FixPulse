<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanArtifact extends Model
{
    protected $fillable = [
        'scan_id',
        'kind',
        'path',
        'meta_jsonb',
    ];

    protected function casts(): array
    {
        return [
            'meta_jsonb' => 'array',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
