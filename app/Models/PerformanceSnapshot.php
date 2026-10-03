<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceSnapshot extends Model
{
    protected $fillable = [
        'company_id', 'department_id', 'bsc_perspective_id',
        'strategic_objective_id', 'scope', 'year', 'month',
        'achievement_pct', 'kpi_count',
        'kpi_high', 'kpi_medium', 'kpi_low', 'kpi_not_entered',
        'calculated_at',
    ];

    protected $casts = [
        'achievement_pct' => 'decimal:4',
        'calculated_at'   => 'datetime',
    ];
}
