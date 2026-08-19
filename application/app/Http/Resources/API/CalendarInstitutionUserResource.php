<?php

namespace App\Http\Resources\API;

use App\Models\CachedEntities\InstitutionUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Lightweight institution user projection for calendar vendor maps.
 *
 * @mixin InstitutionUser
 */
#[OA\Schema(
    title: 'Calendar Institution User',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'user', properties: [
            new OA\Property(property: 'forename', type: 'string'),
            new OA\Property(property: 'surname', type: 'string'),
        ], type: 'object'),
    ],
    type: 'object'
)]
class CalendarInstitutionUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'forename' => data_get($this->user, 'forename'),
                'surname' => data_get($this->user, 'surname'),
            ],
        ];
    }
}
