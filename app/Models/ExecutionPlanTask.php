<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionPlanTask extends Model
{
    protected $fillable = [
        'initiative_id', 'company_id', 'assigned_to',
        'task_name', 'task_name_ar', 'due_date',
        'status', 'completion_pct', 'sort_order',
    ];

    protected $casts = [
        'due_date'       => 'date',
        'completion_pct' => 'decimal:2',
    ];

    public function initiative(): BelongsTo { return $this->belongsTo(Initiative::class); }
    public function assignee(): BelongsTo   { return $this->belongsTo(User::class, 'assigned_to'); }
}
