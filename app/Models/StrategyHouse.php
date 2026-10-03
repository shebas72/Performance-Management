<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StrategyHouse extends Model
{
    protected $fillable = [
        'company_id', 'year', 'mission', 'mission_ar',
        'vision', 'vision_ar', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function coreValues(): HasMany { return $this->hasMany(CoreValue::class)->orderBy('sort_order'); }
}
