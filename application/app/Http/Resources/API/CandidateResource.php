<?php

namespace App\Http\Resources\API;

use App\Models\Candidate;
use App\Services\Prices\AssigneePriceCalculator;
use App\Services\Prices\CandidatePriceCalculator;
use App\Services\Prices\PriceCalculator;
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
            'status' => $this->status,
            'price' => filled($this->vendor) ?
                $this->getPriceCalculator()
                    ->getPrice() : null,
        ];
    }

    private function getPriceCalculator(): PriceCalculator
    {
        if (filled($this->assignment->assigned_vendor_id) &&
            $this->assignment->assigned_vendor_id === $this->vendor?->id) {
            return new AssigneePriceCalculator($this->assignment);
        }

        return new CandidatePriceCalculator(
            $this->assignment,
            $this->vendor
        );
    }
}
