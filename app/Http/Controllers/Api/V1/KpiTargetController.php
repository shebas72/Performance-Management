<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\KpiTarget;
use App\Services\KpiChangeLogger;
use Illuminate\Support\Facades\Auth;

class KpiTargetController extends CompanyResourceController
{
    protected string $model = KpiTarget::class;

    public function __construct(private KpiChangeLogger $changes) {}

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

    protected function afterCreate($record): void
    {
        $this->changes->targetSaved($record, null, Auth::id());
    }

    protected function afterUpdate($record, array $before): void
    {
        $this->changes->targetSaved($record, $before, Auth::id());
    }

    protected function afterDelete($record): void
    {
        $this->changes->targetDeleted($record, Auth::id());
    }
}
