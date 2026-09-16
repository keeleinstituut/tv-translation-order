<?php

namespace App\Http\Resources\API;

use App\Models\InstitutionSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin InstitutionSetting
 */
#[OA\Schema(
    title: 'InstitutionSetting',
    properties: [
        new OA\Property(property: 'reaction_time_minutes', type: 'integer', example: 30),
        new OA\Property(property: 'buffer_before_minutes', type: 'integer', example: 0),
        new OA\Property(property: 'buffer_after_minutes', type: 'integer', example: 0),
        new OA\Property(property: 'verbal_auto_acceptance_threshold_days', type: 'integer', example: 7, nullable: true),
        new OA\Property(property: 'non_verbal_auto_acceptance_threshold_days', type: 'integer', example: 14, nullable: true),
    ],
    type: 'object'
)]
class InstitutionSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->only(
            'reaction_time_minutes',
            'buffer_before_minutes',
            'buffer_after_minutes',
            'verbal_auto_acceptance_threshold_days',
            'non_verbal_auto_acceptance_threshold_days'
        );
    }
}
