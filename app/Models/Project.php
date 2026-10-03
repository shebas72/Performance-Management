<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'company_id', 'department_id', 'strategic_objective_id', 'owner_id',
        'code', 'name', 'name_ar', 'description', 'description_ar',
        'type', 'planned_start_date', 'planned_end_date',
        'actual_start_date', 'actual_end_date',
        'completion_pct', 'is_on_timeline', 'status', 'year', 'is_active',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date'   => 'date',
        'actual_start_date'  => 'date',
        'actual_end_date'    => 'date',
        'completion_pct'     => 'decimal:2',
        'is_on_timeline'     => 'boolean',
        'is_active'          => 'boolean',
    ];

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function department(): BelongsTo { return $this->belongsTo(Department::class); }
    public function objective(): BelongsTo  { return $this->belongsTo(StrategicObjective::class, 'strategic_objective_id'); }
    public function owner(): BelongsTo      { return $this->belongsTo(User::class, 'owner_id'); }
    public function initiatives(): HasMany  { return $this->hasMany(Initiative::class); }
}
