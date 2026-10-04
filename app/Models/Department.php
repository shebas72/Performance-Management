<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use BelongsToCompany;
    protected $fillable = [
        'company_id', 'parent_id', 'manager_id',
        'name', 'name_ar', 'code', 'color', 'sort_order', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function parent(): BelongsTo   { return $this->belongsTo(Department::class, 'parent_id'); }
    public function children(): HasMany   { return $this->hasMany(Department::class, 'parent_id'); }
    public function manager(): BelongsTo  { return $this->belongsTo(User::class, 'manager_id'); }
    public function users(): HasMany      { return $this->hasMany(User::class); }
    public function kpis(): HasMany       { return $this->hasMany(Kpi::class); }
    public function projects(): HasMany   { return $this->hasMany(Project::class); }
    public function initiatives(): HasMany { return $this->hasMany(Initiative::class); }
}
