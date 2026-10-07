<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Department;
use Illuminate\Http\Request;
use App\Models\Kpi;

class DepartmentController extends CompanyResourceController
{
    protected string $model = Department::class;
    protected array $filterable = ['parent_id'];

    protected function rules(): array
    {
        return [
          'name'       => ['required', 'string', 'max:255'],
        'name_ar'    => ['nullable', 'string', 'max:255'],
        'code'       => ['nullable', 'string', 'max:50'],
        'color'      => ['nullable', 'string', 'max:20'],
        'sort_order' => ['nullable', 'integer', 'min:0'],
        'parent_id'  => ['nullable', $this->existsInCompany('departments')],
        ];
    }
    public function update(Request $request, $id)
{
    $this->authorizeWrite($request);

    // Walk up from the proposed parent; meeting this department means the move would create a cycle.
    if ($request->filled('parent_id')) {
        $dept = Department::findOrFail($id);
        $cursor = (int) $request->input('parent_id');
        $seen = [];
        while ($cursor && ! in_array($cursor, $seen, true)) {
            abort_if($cursor === (int) $dept->id, 422, 'A department cannot be moved under itself or one of its own sub-departments.');
            $seen[] = $cursor;
            $cursor = (int) Department::whereKey($cursor)->value('parent_id');
        }
    }

    return parent::update($request, $id);
}

public function destroy(Request $request, $id)
{
    $this->authorizeWrite($request);

    $dept = Department::findOrFail($id);
    abort_if($dept->children()->exists(), 409, 'Move or delete the sub-departments of this department first.');
    abort_if(Kpi::where('department_id', $dept->id)->exists(), 409, 'Reassign the KPIs of this department first.');

    return parent::destroy($request, $id);
}
}
