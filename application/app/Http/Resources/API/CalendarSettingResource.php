<?php

namespace App\Http\Resources\API;

use App\Models\CalendarSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin CalendarSetting
 */
#[OA\Schema(
    title: 'CalendarSetting',
    properties: [
        new OA\Property(property: 'reaction_time_minutes', type: 'integer', example: 30),
        new OA\Property(property: 'buffer_before_minutes', type: 'integer', example: 0),
        new OA\Property(property: 'buffer_after_minutes', type: 'integer', example: 0),
        new OA\Property(property: 'default_project_type_id', type: 'string', format: 'uuid', nullable: true),
    ],
    type: 'object'
)]
class CalendarSettingResource extends JsonResource
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
            'default_project_type_id'
        );
    }
}
