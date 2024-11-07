<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'CatToolTm',
    required: [
        'cat_tm_key',
        'cat_tm_meta'
    ],
    properties: [
        new OA\Property(property: 'cat_tm_key', ref: CatToolTmKeyResource::class),
        new OA\Property(property: 'cat_tm_meta', description: 'The response from creating TM inside TM service', type: 'object'),
    ],
    type: 'object'
)]
class CreatedCatToolTmKeyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'cat_tm_key' => CatToolTmKeyResource::make(data_get($this, 'key')),
            'cat_tm_meta' => data_get($this, 'meta'),
        ];
    }
}
