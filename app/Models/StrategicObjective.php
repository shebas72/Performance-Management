<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategicObjective extends Model
{
    protected $fillable = [
        'company_id', 'bsc_perspective_id', 'strategy_house_id',
        'code', 'name', 'name_ar', 'description', 'description_ar',
        'weight', 'year', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'weight'    => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo     { return $this->belongsTo(Company::class); }
    public function perspective(): BelongsTo { return $this->belongsTo(BscPerspective::class, 'bsc_perspective_id'); }
    public function strategyHouse(): BelongsTo { return $this->belongsTo(StrategyHouse::class); }
    public function kpis(): HasMany          { return $this->hasMany(Kpi::class); }
    public function initiatives(): HasMany   { return $this->hasMany(Initiative::class); }
    public function projects(): HasMany      { return $this->hasMany(Project::class); }
}
