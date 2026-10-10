<?php
// ─────────────────────────────────────────────
// Company.php
// ─────────────────────────────────────────────
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Company extends Model
{
    protected $fillable = [
        'name', 'name_ar', 'slug', 'logo', 'timezone',
        'default_language', 'plan', 'app_mode',
        'fiscal_year_start', 'trial_ends_at', 'is_active', 'team_report_visibility',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany { return $this->hasMany(User::class); }
    public function departments(): HasMany { return $this->hasMany(Department::class); }
    public function strategyHouses(): HasMany { return $this->hasMany(StrategyHouse::class); }
    public function bscPerspectives(): HasMany { return $this->hasMany(BscPerspective::class); }
    public function strategicObjectives(): HasMany { return $this->hasMany(StrategicObjective::class); }
    public function kpis(): HasMany { return $this->hasMany(Kpi::class); }
    public function projects(): HasMany { return $this->hasMany(Project::class); }
    public function initiatives(): HasMany { return $this->hasMany(Initiative::class); }
    public function subscription(): HasOne { return $this->hasOne(Subscription::class)->latestOfMany(); }

    public function activeStrategyHouse(): HasOne
    {
        return $this->hasOne(StrategyHouse::class)->where('is_active', true)->latestOfMany();
    }
}
