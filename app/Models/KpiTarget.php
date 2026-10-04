<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiTarget extends Model
{
    use BelongsToCompany;
    protected $fillable = [
        'kpi_id', 'company_id', 'year', 'month',
        'target_value', 'recovery_target',
    ];

    protected $casts = [
        'target_value'    => 'decimal:4',
        'recovery_target' => 'decimal:4',
    ];

    public function kpi(): BelongsTo { return $this->belongsTo(Kpi::class); }
}
