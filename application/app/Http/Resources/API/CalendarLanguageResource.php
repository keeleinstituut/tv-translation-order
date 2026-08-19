<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Calendar Languages',
    description: 'For vendor callers, `main_languages` and `pinned_languages` are always empty arrays.',
    required: ['project_languages'],
    properties: [
        new OA\Property(
            property: 'main_languages',
            type: 'array',
            items: new OA\Items(ref: InstitutionMainLanguageResource::class)
        ),
        new OA\Property(
            property: 'pinned_languages',
            type: 'array',
            items: new OA\Items(ref: InstitutionUserPinnedLanguageResource::class)
        ),
        new OA\Property(
            property: 'project_languages',
            type: 'array',
            items: new OA\Items(ref: ClassifierValueResource::class)
        ),
    ],
    type: 'object'
)]
class CalendarLanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'main_languages' => InstitutionMainLanguageResource::collection($this['main_languages'] ?? []),
            'pinned_languages' => InstitutionUserPinnedLanguageResource::collection($this['pinned_languages'] ?? []),
            'project_languages' => ClassifierValueResource::collection($this['project_languages'] ?? []),
        ];
    }
}
