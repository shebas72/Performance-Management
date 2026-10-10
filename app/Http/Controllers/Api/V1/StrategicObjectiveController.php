<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\StrategicObjective;
use App\Models\StrategyHouse;

class StrategicObjectiveController extends CompanyResourceController
{
    protected string $model = StrategicObjective::class;
    protected array $filterable = ['bsc_perspective_id'];
    protected array $writeRoles = ['admin']; // strategy is set by company admins only

    protected function rules(): array
    {
        return [
            'bsc_perspective_id' => ['required', $this->existsInCompany('bsc_perspectives')],
            'name'               => ['required', 'string', 'max:255'],
            'name_ar'            => ['nullable', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'description_ar'     => ['nullable', 'string'],
            'weight'             => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order'         => ['nullable', 'integer', 'min:0'],
            'strategy_house_id'  => ['nullable', $this->existsInCompany('strategy_houses')],
            'code'               => ['required', 'string', 'max:255'],
            'year'               => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    protected function beforeCreate(array $data): array
{
    if (empty($data['strategy_house_id'])) {
        $houseId = StrategyHouse::value('id');
        abort_if(! $houseId, 422, 'Create the strategy house first (PUT /api/v1/strategy-house).');
        $data['strategy_house_id'] = $houseId;
    }

    return $data;
}
}
