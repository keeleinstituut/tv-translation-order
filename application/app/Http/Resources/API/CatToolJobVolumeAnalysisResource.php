<?php

namespace App\Http\Resources\API;

use App\Models\CatToolJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin CatToolJob
 */
#[OA\Schema(
    required: [
        'id',
        'name',
        'volume_analysis',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'volume_analysis', ref: VolumeAnalysisResource::class, nullable: true),
    ],
    type: 'object'
)]
class CatToolJobVolumeAnalysisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only([
                'id',
                'name',
            ]),
            'volume_analysis' => VolumeAnalysisResource::make($this->getVolumeAnalysis()),
        ];
    }
}
