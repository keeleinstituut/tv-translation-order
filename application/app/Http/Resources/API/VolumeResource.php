<?php

namespace App\Http\Resources\API;

use App\Enums\VolumeUnits;
use App\Models\Volume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin Volume */
#[OA\Schema(
    title: 'Volume',
    required: [
        'id',
        'assignment_id',
        'cat_tool_job_id',
        'unit_type',
        'unit_quantity',
        'unit_fee',
        'custom_volume_analysis',
        'custom_discounts',
        'created_at',
        'updated_at',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'cat_tool_job_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'unit_type', type: 'string', enum: VolumeUnits::class),
        new OA\Property(property: 'unit_quantity', type: 'number', minimum: 0),
        new OA\Property(property: 'unit_fee', type: 'number', minimum: 0),
        new OA\Property(property: 'job', ref: CatToolJobResource::class),
        new OA\Property(property: 'volume_analysis', ref: VolumeAnalysisResource::class),
        new OA\Property(property: 'discount', ref: VolumeAnalysisDiscountResource::class),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class VolumeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(
                'id',
                'assignment_id',
                'unit_type',
                'unit_quantity',
                'unit_fee',
                'updated_at',
                'created_at'
            ),
            'job' => CatToolJobResource::make($this->catToolJob),
            'volume_analysis' => VolumeAnalysisResource::make($this->getVolumeAnalysis()),
            'discount' => VolumeAnalysisDiscountResource::make($this->getDiscount()),
        ];
    }
}
