<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Kpi;
use App\Services\KpiChangeLogger;
use Illuminate\Support\Facades\Auth;

class KpiController extends CompanyResourceController
{
    protected string $model = Kpi::class;

    public function __construct(private KpiChangeLogger $changes) {}

    protected array $searchable = ['name', 'name_ar', 'code'];
    protected array $filterable = [
        'strategic_objective_id', 'bsc_perspective_id', 'department_id',
        'owner_id', 'type', 'frequency', 'direction', 'year',
    ];

    protected function rules(): array
    {
        return [
            'strategic_objective_id' => ['nullable', $this->existsInCompany('strategic_objectives')],
            'bsc_perspective_id'     => ['nullable', $this->existsInCompany('bsc_perspectives')],
            'department_id'          => ['nullable', $this->existsInCompany('departments')],
            'owner_id'               => ['nullable', $this->existsInCompany('users')],

            'code'                   => ['required', 'string', 'max:255'],
            'name'                   => ['required', 'string', 'max:255'],
            'name_ar'                => ['nullable', 'string', 'max:255'],
            'description'            => ['nullable', 'string'],
            'description_ar'         => ['nullable', 'string'],
            'year'                   => ['required', 'integer', 'min:2000', 'max:2100'],

            // These columns have DB defaults, so they are only validated when sent.
            'type'                   => ['sometimes', 'in:strategic,operational'],
            'frequency'              => ['sometimes', 'in:monthly,quarterly,yearly'],
            'direction'              => ['sometimes', 'in:higher_is_better,lower_is_better'],
            'value_type'             => ['sometimes', 'in:percentage,number,currency,ratio'],
            'weight'                 => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'threshold_red'          => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'threshold_yellow'       => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'has_recovery_target'    => ['sometimes', 'boolean'],
            'is_active'              => ['sometimes', 'boolean'],

            'unit'                   => ['nullable', 'string', 'max:50'],
            'annual_target'          => ['nullable', 'numeric'],
        ];
    }

    protected function beforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate($record): void
    {
        $this->changes->kpiCreated($record, Auth::id());
    }

    protected function afterUpdate($record, array $before): void
    {
        $this->changes->kpiUpdated($record, $before, Auth::id());
    }
}