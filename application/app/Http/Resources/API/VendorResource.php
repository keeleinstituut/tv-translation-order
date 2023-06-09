<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorResource extends JsonResource
{
    // public withDiscounts()

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
            'institution_user' => new InstitutionUserResource($this->whenLoaded('institutionUser')),
            'prices' => PriceResource::collection($this->whenLoaded('prices')),
            ...$this->discounts(),
        ];
    }

    private function discounts() {
        // TODO: figure out how to conditionally render certain
        // TODO: json fields based on controllers input.
        // TODO: ideally should be recursive to nested resources as well.

        // if (!data_get($this->additional, 'withDiscounts')) {
        //     return [];
        // }

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
