<?php

namespace App\Http\Resources\API;

use App\Models\InstitutionMainLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin InstitutionMainLanguage
 */
#[OA\Schema(
    title: 'Institution Main Language',
    required: ['id', 'institution_id', 'language_id', 'language'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institution_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'language_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'language', ref: ClassifierValueResource::class),
    ],
    type: 'object'
)]
class InstitutionMainLanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'language_id' => $this->language_id,
            'language' => ClassifierValueResource::make(
                $this->whenLoaded('language')
            ),
        ];
    }
}
