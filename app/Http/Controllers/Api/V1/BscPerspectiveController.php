<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BscPerspective;

class BscPerspectiveController extends CompanyResourceController
{
    protected string $model = BscPerspective::class;

    protected function rules(): array
    {
        return [
            'name'           => ['required', 'string', 'max:255'],
            'name_ar'        => ['nullable', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'color'          => ['nullable', 'string', 'max:20'],
            'weight'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order'     => ['nullable', 'integer', 'min:0'],
            'code'           => ['required', 'string', 'max:255'],
            
        ];
    }
}
