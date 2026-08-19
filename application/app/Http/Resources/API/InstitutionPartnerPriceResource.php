<?php

namespace App\Http\Resources\API;

use App\Models\InstitutionPartnerPrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin InstitutionPartnerPrice
 */
#[OA\Schema(
    title: 'InstitutionPartnerPrice',
    required: [
        'id', 'institution_partner_id', 'skill_id',
        'src_lang_classifier_value_id', 'dst_lang_classifier_value_id',
        'created_at', 'updated_at',
        'character_fee', 'word_fee', 'page_fee', 'minute_fee', 'hour_fee', 'minimal_fee',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institution_partner_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'skill_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'src_lang_classifier_value_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'dst_lang_classifier_value_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'character_fee', type: 'number', format: 'double'),
        new OA\Property(property: 'word_fee', type: 'number', format: 'double'),
        new OA\Property(property: 'page_fee', type: 'number', format: 'double'),
        new OA\Property(property: 'minute_fee', type: 'number', format: 'double'),
        new OA\Property(property: 'hour_fee', type: 'number', format: 'double'),
        new OA\Property(property: 'minimal_fee', type: 'number', format: 'double'),
        new OA\Property(property: 'institution_partner', ref: InstitutionPartnerResource::class, type: 'object'),
        new OA\Property(property: 'source_language_classifier_value', ref: ClassifierValueResource::class, type: 'object'),
        new OA\Property(property: 'destination_language_classifier_value', ref: ClassifierValueResource::class, type: 'object'),
        new OA\Property(property: 'skill', ref: SkillResource::class, type: 'object'),
    ],
    type: 'object'
)]
class InstitutionPartnerPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_partner_id' => $this->institution_partner_id,
            'skill_id' => $this->skill_id,
            'src_lang_classifier_value_id' => $this->src_lang_classifier_value_id,
            'dst_lang_classifier_value_id' => $this->dst_lang_classifier_value_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'character_fee' => $this->character_fee,
            'word_fee' => $this->word_fee,
            'page_fee' => $this->page_fee,
            'minute_fee' => $this->minute_fee,
            'hour_fee' => $this->hour_fee,
            'minimal_fee' => $this->minimal_fee,
            'institution_partner' => InstitutionPartnerResource::make($this->whenLoaded('institutionPartner')),
            'source_language_classifier_value' => ClassifierValueResource::make($this->whenLoaded('sourceLanguageClassifierValue')),
            'destination_language_classifier_value' => ClassifierValueResource::make($this->whenLoaded('destinationLanguageClassifierValue')),
            'skill' => SkillResource::make($this->whenLoaded('skill')),
        ];
    }
}
