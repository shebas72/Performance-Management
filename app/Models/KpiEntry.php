<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiEntry extends Model
{
    protected $fillable = [
        'kpi_id', 'company_id', 'logged_by',
        'year', 'month', 'actual_value', 'achievement_pct', 'status',
        'note', 'note_ar', 'data_status', 'incomplete_reason', 'submitted_at',
    ];

    protected $casts = [
        'actual_value'    => 'decimal:4',
        'achievement_pct' => 'decimal:4',
        'submitted_at'    => 'datetime',
    ];

    public function kpi(): BelongsTo    { return $this->belongsTo(Kpi::class); }
    public function logger(): BelongsTo { return $this->belongsTo(User::class, 'logged_by'); }

    public function getMonthNameAttribute(): string
    {
        return \Carbon\Carbon::create(null, $this->month)->format('M');
    }
}
