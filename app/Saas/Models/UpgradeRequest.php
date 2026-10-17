<?php

namespace App\Saas\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UpgradeRequest extends Model
{
    protected $guarded = [];

    protected $casts = ['decided_at' => 'datetime'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function plan(): BelongsTo { return $this->belongsTo(Plan::class); }
}