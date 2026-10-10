<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\CoreValue;
use App\Models\StrategyHouse;

class CoreValueController extends CompanyResourceController
{
    protected string $model = CoreValue::class;
    protected array $writeRoles = ['admin']; // strategy is set by company admins only

    protected function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'name_ar'        => ['nullable', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'icon'           => ['nullable', 'string', 'max:100'],
            'color'          => ['nullable', 'string', 'max:20'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function beforeCreate(array $data): array
    {
        $houseId = StrategyHouse::value('id');
        abort_if(! $houseId, 422, 'Create the strategy house first (PUT /api/v1/strategy-house).');
        $data['strategy_house_id'] = $houseId;

        return $data;
    }
}