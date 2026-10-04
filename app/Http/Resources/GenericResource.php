<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One consistent JSON shape for all Phase 3 models.
 * Returns every attribute (both languages), hides company_id,
 * and adds a "label" resolved from the request locale.
 */
class GenericResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        unset($data['company_id']);

        $isAr = app()->getLocale() === 'ar';
        $data['label'] = $isAr && ! empty($data['name_ar'])
            ? $data['name_ar']
            : ($data['name'] ?? null);

        return $data;
    }
}
