<?php

namespace App\Http\Resources\API;

use App\Models\Candidate;
use App\Services\Prices\CandidatePriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Candidate
 */
class CandidateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'vendor' => VendorResource::make($this->vendor),
            'price' => filled($this->vendor) ?
                (new CandidatePriceCalculator(
                    $this->assignment,
                    $this->vendor
                ))->getPrice() : null,
        ];
    }
}
