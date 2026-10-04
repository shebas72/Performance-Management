<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Kpi extends Model
{
    use BelongsToCompany;
    protected $fillable = [
        'company_id', 'strategic_objective_id', 'bsc_perspective_id',
        'department_id', 'owner_id', 'created_by',
        'code', 'name', 'name_ar', 'description', 'description_ar',
        'type', 'frequency', 'direction', 'value_type', 'unit',
        'weight', 'annual_target', 'year',
        'threshold_red', 'threshold_yellow',
        'has_recovery_target', 'is_active',
    ];

    protected $casts = [
        'weight'              => 'decimal:2',
        'annual_target'       => 'decimal:4',
        'threshold_red'       => 'decimal:2',
        'threshold_yellow'    => 'decimal:2',
        'has_recovery_target' => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function company(): BelongsTo           { return $this->belongsTo(Company::class); }
    public function strategicObjective(): BelongsTo { return $this->belongsTo(StrategicObjective::class); }
    public function perspective(): BelongsTo        { return $this->belongsTo(BscPerspective::class, 'bsc_perspective_id'); }
    public function department(): BelongsTo         { return $this->belongsTo(Department::class); }
    public function owner(): BelongsTo              { return $this->belongsTo(User::class, 'owner_id'); }
    public function targets(): HasMany              { return $this->hasMany(KpiTarget::class)->orderBy('month'); }
    public function entries(): HasMany              { return $this->hasMany(KpiEntry::class)->orderBy('month'); }
    public function correctiveProposals(): HasMany  { return $this->hasMany(CorrectiveProposal::class); }
    public function initiatives(): BelongsToMany
    {
        return $this->belongsToMany(Initiative::class, 'initiative_kpi');
    }

    public function scopeStrategic($query)          { return $query->where('type', 'strategic'); }
    public function scopeOperational($query)        { return $query->where('type', 'operational'); }
    public function scopeForYear($query, int $year) { return $query->where('year', $year); }

    public function calculateAchievement(float $actual, float $target): float
    {
        if ($target == 0) return 0;
        if ($this->direction === 'lower_is_better') {
            return ($target / $actual) * 100;
        }
        return ($actual / $target) * 100;
    }

    public function getStatus(float $achievementPct): string
    {
        return match(true) {
            $achievementPct >= $this->threshold_yellow => 'on_track',
            $achievementPct >= $this->threshold_red    => 'at_risk',
            default                                    => 'behind',
        };
    }
}
