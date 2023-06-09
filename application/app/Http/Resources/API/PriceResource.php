<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            //
            // TODO: add skill
            //
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
            'vendor' => new VendorResource($this->whenLoaded('vendor')),
            'source_language_classifier_value' => new ClassifierValueResource($this->whenLoaded('sourceLanguageClassifierValue')),
            'destination_language_classifier_value' => new ClassifierValueResource($this->whenLoaded('destinationLanguageClassifierValue')),
        ];
    }
}
