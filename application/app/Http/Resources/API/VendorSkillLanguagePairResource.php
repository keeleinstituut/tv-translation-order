<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
