<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BscPerspective extends Model
{
    use BelongsToCompany;
    protected $fillable = [
        'company_id', 'name', 'name_ar', 'code',
        'color', 'weight', 'sort_order',
    ];

    protected $casts = ['weight' => 'decimal:2'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function strategicObjectives(): HasMany
    {
        return $this->hasMany(StrategicObjective::class)->orderBy('sort_order');
    }

    public function kpis(): HasMany { return $this->hasMany(Kpi::class); }
}
