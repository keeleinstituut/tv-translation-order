<?php

namespace App\Http\Resources\API;

use App\Models\VendorSkillLanguage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin VendorSkillLanguage
 */
#[OA\Schema(
    title: 'VendorSkillLanguage',
    required: [
        'id', 'vendor_id', 'skill_id', 'src_lang_classifier_value_id', 'dst_lang_classifier_value_id',
        'created_at', 'updated_at',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'skill_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'src_lang_classifier_value_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'dst_lang_classifier_value_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'vendor', ref: VendorResource::class, type: 'object'),
        new OA\Property(property: 'source_language_classifier_value', ref: ClassifierValueResource::class, type: 'object'),
        new OA\Property(property: 'destination_language_classifier_value', ref: ClassifierValueResource::class, type: 'object'),
        new OA\Property(property: 'skill', ref: SkillResource::class, type: 'object'),
    ],
    type: 'object'
)]
class VendorSkillLanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'skill_id' => $this->skill_id,
            'src_lang_classifier_value_id' => $this->src_lang_classifier_value_id,
            'dst_lang_classifier_value_id' => $this->dst_lang_classifier_value_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'vendor' => VendorResource::make($this->whenLoaded('vendor')),
            'source_language_classifier_value' => ClassifierValueResource::make($this->whenLoaded('sourceLanguageClassifierValue')),
            'destination_language_classifier_value' => ClassifierValueResource::make($this->whenLoaded('destinationLanguageClassifierValue')),
            'skill' => SkillResource::make($this->whenLoaded('skill')),
        ];
    }
}
