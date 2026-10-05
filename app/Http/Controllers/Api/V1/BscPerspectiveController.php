<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BscPerspective;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function updateWeights(Request $request)
{
    abort_unless($request->user()->hasAnyRole(['admin', 'manager']), 403, 'You do not have permission to modify this resource.');

    $data = $request->validate([
        'weights'          => ['required', 'array', 'min:1'],
        'weights.*.id'     => ['required', 'integer'],
        'weights.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
    ]);

    $incoming     = collect($data['weights'])->pluck('weight', 'id');
    $perspectives = BscPerspective::all(); // company-scoped like the other queries

    abort_unless(
        $incoming->count() === $perspectives->count() && $perspectives->pluck('id')->diff($incoming->keys())->isEmpty(),
        422, 'A weight is required for every perspective.'
    );
    abort_unless(abs($incoming->sum() - 100) < 0.01, 422, 'Perspective weights must add up to 100.');

    DB::transaction(fn () => $perspectives->each(
        fn ($p) => $p->forceFill(['weight' => $incoming[$p->id]])->save()
    ));

    return response()->json(['data' => BscPerspective::orderBy('id')->get(['id', 'weight'])]);
}
}
