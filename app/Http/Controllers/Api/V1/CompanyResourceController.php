<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GenericResource;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Generic tenant-scoped CRUD. Tenant isolation comes from the
 * BelongsToCompany global scope on the model, so findOrFail() on
 * another company's record returns 404.
 */
abstract class CompanyResourceController extends Controller
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    protected string $model;

    protected array $with = [];
    protected array $searchable = ['name', 'name_ar'];
    protected array $filterable = [];
    protected string $orderBy = 'id';
    protected array $writeRoles = ['admin', 'manager'];

    /** Validation rules (array syntax). "sometimes" is added automatically on update. */
    abstract protected function rules(): array;

    protected function beforeCreate(array $data): array
{
    return $data;
}

    /** Hooks for audit logging etc. No-ops by default. $before = raw attributes prior to the update. */
    protected function afterCreate($record): void {}
    protected function afterUpdate($record, array $before): void {}
    protected function afterDelete($record): void {}


    public function index(Request $request)
    {
        $query = ($this->model)::query()->with($this->with)->orderBy($this->orderBy);

        foreach ($this->filterable as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($search = $request->input('search')) {
            $query->where(function ($w) use ($search) {
                foreach ($this->searchable as $col) {
                    $w->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

        $perPage = min(max((int) $request->input('per_page', 25), 1), 100);

        return GenericResource::collection($query->paginate($perPage));
    }

    public function show($id)
    {
        $record = ($this->model)::with($this->with)->findOrFail($id);

        return new GenericResource($record);
    }

   public function store(Request $request)
{
    $this->authorizeWrite($request);

    $data = $this->beforeCreate($request->validate($this->rules()));

    $class = $this->model;
    $record = new $class();
    $record->forceFill($data)->save();
    $this->afterCreate($record);

    return (new GenericResource($record->load($this->with)))
        ->response()
        ->setStatusCode(201);
}

public function update(Request $request, $id)
{
    $this->authorizeWrite($request);

    $record = ($this->model)::findOrFail($id);
    $before = $record->getAttributes();

    $rules = collect($this->rules())
        ->map(fn ($r) => array_merge(['sometimes'], (array) $r))
        ->all();

    $record->forceFill($request->validate($rules))->save();
    $this->afterUpdate($record, $before);

    return new GenericResource($record->fresh($this->with));
}

    public function destroy(Request $request, $id)
    {
        $this->authorizeWrite($request);

        $record = ($this->model)::findOrFail($id);

        try {
            $record->delete();
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'This record is in use and cannot be deleted.',
            ], 409);
        }

        $this->afterDelete($record);

        return response()->noContent();
    }

    protected function authorizeWrite(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && $user->hasAnyRole($this->writeRoles),
            403,
            'You do not have permission to modify this resource.'
        );
    }

    /** exists:{table},id restricted to the caller's company (prevents cross-tenant FKs). */
    protected function existsInCompany(string $table)
    {
        return Rule::exists($table, 'id')->where('company_id', Auth::user()->company_id);
    }
}
