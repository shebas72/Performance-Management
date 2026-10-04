<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\KpiTarget;

class KpiTargetController extends CompanyResourceController
{
    protected string $model = KpiTarget::class;
    protected array $searchable = [];
    protected array $filterable = ['kpi_id', 'year', 'month'];

    protected function rules(): array
    {
        return [
            'kpi_id'       => ['required', $this->existsInCompany('kpis')],
            'year'         => ['required', 'integer', 'min:2000', 'max:2100'],
           
            'target_value' => ['required', 'numeric'],
            'month'        => ['required', 'integer', 'min:1', 'max:12'],
        ];
    }
}
