<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Department;
use App\Models\Kpi;
use App\Services\AccessScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DepartmentController extends CompanyResourceController
{
    protected string $model = Department::class;
    protected array $filterable = ['parent_id'];

    public function __construct(private AccessScope $access) {}

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

    /** Restricted users only see their departments; a department whose parent is hidden is shown as a top-level one. */
    protected function scoped($query)
    {
        $ids = $this->access->viewIds(Auth::user());

        return $ids === null ? $query : $query->whereIn('id', $ids);
    }

    protected function beforeCreate(array $data): array
    {
        // A new department has to hang under one the user may edit; only unrestricted editors can add a top-level one.
        $this->guard($data['parent_id'] ?? null);

        return $data;
    }

    protected function beforeUpdate($record, array $data): array
    {
        $this->guard((int) $record->id);
        if (! empty($data['parent_id'])) $this->guard((int) $data['parent_id']);

        return $data;
    }

    protected function beforeDelete($record): void
    {
        $this->guard((int) $record->id);
    }

    private function guard($departmentId): void
    {
        abort_unless(
            $this->access->canEdit(Auth::user(), $departmentId === null ? null : (int) $departmentId),
            403,
            'You can only manage your own departments.'
        );
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
