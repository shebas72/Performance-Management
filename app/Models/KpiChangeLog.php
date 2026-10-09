<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiChangeLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id', 'kpi_id', 'user_id', 'action', 'field',
        'period_year', 'period_month', 'old_value', 'new_value',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function kpi(): BelongsTo  { return $this->belongsTo(Kpi::class); }
}
