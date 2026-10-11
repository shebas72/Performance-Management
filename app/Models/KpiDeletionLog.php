<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A record that a KPI was deleted. The KPI's own change history is deleted with it, so this is what is left. */
class KpiDeletionLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id', 'kpi_id', 'user_id', 'code', 'name', 'name_ar', 'year',
        'department_id', 'department_name', 'entries_count', 'targets_count',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
