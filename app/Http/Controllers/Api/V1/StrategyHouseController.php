<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GenericResource;
use App\Models\BscPerspective;
use App\Models\CoreValue;
use App\Models\StrategyHouse;
use Illuminate\Http\Request;

class StrategyHouseController extends Controller
{
    public function show()
    {
        $house = StrategyHouse::first();

        return response()->json([
            'data' => [
                'house'        => $house ? new GenericResource($house) : null,
                'core_values'  => GenericResource::collection(CoreValue::orderBy('id')->get()),
                'perspectives' => GenericResource::collection(BscPerspective::orderBy('id')->get()),
            ],
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['admin', 'manager']), 403, 'You do not have permission to modify this resource.');

        $data = $request->validate([
            'year'       => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'mission'    => ['nullable', 'string'],
            'mission_ar' => ['nullable', 'string'],
            'vision'     => ['nullable', 'string'],
            'vision_ar'  => ['nullable', 'string'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        if (empty($data['year'])) {
            unset($data['year']);
        }

        $house = StrategyHouse::first() ?? new StrategyHouse();

        if (! $house->exists && ! isset($data['year'])) {
            $data['year'] = now()->year;
        }

        $house->forceFill($data)->save();

        return new GenericResource($house->fresh());
    }
}