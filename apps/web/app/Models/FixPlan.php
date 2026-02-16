<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixPlan extends Model
{
    protected $fillable = [
        'scan_id',
        'plan_jsonb',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'plan_jsonb' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }
}
