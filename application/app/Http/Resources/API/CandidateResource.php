<?php

namespace App\Http\Resources\API;

use App\Enums\CandidateStatus;
use App\Models\Candidate;
use App\Services\Prices\AssigneePriceCalculator;
use App\Services\Prices\CandidatePriceCalculator;
use App\Services\Prices\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Candidate
 */
#[OA\Schema(
    title: 'Candidate',
    required: [
        'vendor',
        'status',
        'price',
    ],
    properties: [
        new OA\Property(property: 'vendor', ref: VendorResource::class),
        new OA\Property(property: 'status', type: 'string', format: 'enum', enum: CandidateStatus::class),
        new OA\Property(property: 'price', type: 'number', nullable: true),
    ],
    type: 'object'
)]
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
