<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'VendorSkillLanguagePair',
    required: [
        'id', 'vendor_id', 'skill_id',
        'src_lang_classifier_value_id', 'dst_lang_classifier_value_id',
        'created_at', 'updated_at',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'skill_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'src_lang_classifier_value_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'dst_lang_classifier_value_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'source_language_classifier_value', ref: ClassifierValueResource::class, type: 'object'),
        new OA\Property(property: 'destination_language_classifier_value', ref: ClassifierValueResource::class, type: 'object'),
        new OA\Property(property: 'skill', ref: SkillResource::class, type: 'object'),
        new OA\Property(property: 'vendor', ref: VendorResource::class, type: 'object'),
    ],
    type: 'object'
)]
class VendorSkillLanguagePairResource extends JsonResource
{
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
            'source_language_classifier_value' => new ClassifierValueResource($this->whenLoaded('sourceLanguageClassifierValue')),
            'destination_language_classifier_value' => new ClassifierValueResource($this->whenLoaded('destinationLanguageClassifierValue')),
            'skill' => new SkillResource($this->whenLoaded('skill')),
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
        ];
    }
}
