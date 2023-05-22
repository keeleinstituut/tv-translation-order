<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;


#[OA\Schema(
    title: 'Classifier Value',
    required: ['id', 'type', 'value', 'name'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'value', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
    ],
    type: 'object'
)]
class ClassifierValueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'value' => $this->value,
            'name' => $this->name,
        ];
    }
}
