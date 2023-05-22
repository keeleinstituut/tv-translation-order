<?php

namespace App\Http\Resources\API;

use App\Http\Resources\TagResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Vendor',
    required: ['id', 'institution_id', 'company_name', 'updated_at', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institution_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'company_name', type: 'string'),
        new OA\Property(property: 'comment', type: 'string'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'discount_percentage_101', type: 'number', format: 'double'),
        new OA\Property(property: 'discount_percentage_repetitions', type: 'number', format: 'double'),
        new OA\Property(property: 'discount_percentage_100', type: 'number', format: 'double'),
        new OA\Property(property: 'discount_percentage_95_99', type: 'number', format: 'double'),
        new OA\Property(property: 'discount_percentage_85_94', type: 'number', format: 'double'),
        new OA\Property(property: 'discount_percentage_75_84', type: 'number', format: 'double'),
        new OA\Property(property: 'discount_percentage_50_74', type: 'number', format: 'double'),
        new OA\Property(property: 'discount_percentage_0_49', type: 'number', format: 'double'),
        new OA\Property(property: 'prices', type: 'array', items: new OA\Items(ref: PriceResource::class)),
        new OA\Property(property: 'institution_user', ref: InstitutionUserResource::class, type: 'object'),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(ref: TagResource::class)),
    ],
    type: 'object'
)]
class VendorResource extends JsonResource
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
            'institution_user_id' => $this->institution_user_id,
            'company_name' => $this->company_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'comment' => $this->comment,
            ...$this->discounts(),
            'prices' => PriceResource::collection($this->whenLoaded('prices')),
            'institution_user' => new InstitutionUserResource($this->whenLoaded('institutionUser')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }

    private function discounts() {
        // TODO: figure out how to conditionally render certain
        // TODO: json fields based on controllers input.
        // TODO: ideally should be recursive to nested resources as well.

        return [
            'discount_percentage_101' => $this->discount_percentage_101,
            'discount_percentage_repetitions' => $this->discount_percentage_repetitions,
            'discount_percentage_100' => $this->discount_percentage_100,
            'discount_percentage_95_99' => $this->discount_percentage_95_99,
            'discount_percentage_85_94' => $this->discount_percentage_85_94,
            'discount_percentage_75_84' => $this->discount_percentage_75_84,
            'discount_percentage_50_74' => $this->discount_percentage_50_74,
            'discount_percentage_0_49' => $this->discount_percentage_0_49,
        ];
    }
}
