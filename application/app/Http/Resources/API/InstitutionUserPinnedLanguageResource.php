<?php

namespace App\Http\Resources\API;

use App\Models\InstitutionUserPinnedLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin InstitutionUserPinnedLanguage
 */
#[OA\Schema(
    title: 'Institution User Pinned Language',
    required: ['id', 'institution_user_id', 'institution_main_language_id', 'language'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institution_user_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institution_main_language_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institution_main_language', ref: InstitutionMainLanguageResource::class),
    ],
    type: 'object'
)]
class InstitutionUserPinnedLanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_user_id' => $this->institution_user_id,
            'institution_main_language_id' => $this->institution_main_language_id,
            'institution_main_language' => InstitutionMainLanguageResource::make(
                $this->whenLoaded('mainLanguage')
            ),
        ];
    }
}
