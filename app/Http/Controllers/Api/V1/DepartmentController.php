<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Department;

class DepartmentController extends CompanyResourceController
{
    protected string $model = Department::class;
    protected array $filterable = ['parent_id'];

    protected function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'name_ar'   => ['nullable', 'string', 'max:255'],
            'code'      => ['nullable', 'string', 'max:50'],
            'parent_id' => ['nullable', $this->existsInCompany('departments')],
        ];
    }
}
