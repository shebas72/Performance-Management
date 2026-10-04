<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreValue extends Model
{
    use BelongsToCompany;
    protected $fillable = [
        'company_id', 'strategy_house_id', 'name', 'name_ar',
        'description', 'description_ar', 'icon', 'color', 'sort_order',
    ];

    public function strategyHouse(): BelongsTo { return $this->belongsTo(StrategyHouse::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
