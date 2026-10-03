<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Initiative extends Model
{
    protected $fillable = [
        'company_id', 'project_id', 'strategic_objective_id',
        'department_id', 'owner_id',
        'code', 'name', 'name_ar', 'description', 'description_ar',
        'planned_start_date', 'planned_end_date',
        'completion_pct', 'is_on_timeline', 'status', 'year',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date'   => 'date',
        'completion_pct'     => 'decimal:2',
        'is_on_timeline'     => 'boolean',
    ];

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function project(): BelongsTo    { return $this->belongsTo(Project::class); }
    public function objective(): BelongsTo  { return $this->belongsTo(StrategicObjective::class, 'strategic_objective_id'); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function owner(): BelongsTo      { return $this->belongsTo(User::class, 'owner_id'); }
    public function tasks(): HasMany        { return $this->hasMany(ExecutionPlanTask::class)->orderBy('sort_order'); }
    public function kpis(): BelongsToMany   { return $this->belongsToMany(Kpi::class, 'initiative_kpi'); }
}
