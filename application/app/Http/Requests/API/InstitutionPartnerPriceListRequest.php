<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class InstitutionPartnerPriceListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'per_page' => 'sometimes|integer|max:50',
            'institution_partner_id' => 'sometimes|uuid',
            'skill_id' => 'sometimes|array',
            'skill_id.*' => 'uuid',
            'src_lang_classifier_value_id' => 'sometimes|array',
            'src_lang_classifier_value_id.*' => 'uuid',
            'dst_lang_classifier_value_id' => 'sometimes|array',
            'dst_lang_classifier_value_id.*' => 'uuid',
            'lang_pair' => 'sometimes|array',
            'lang_pair.*.src' => 'required|uuid',
            'lang_pair.*.dst' => 'required|uuid',
            'sort_by' => 'sometimes|in:character_fee,word_fee,page_fee,minute_fee,hour_fee,minimal_fee,created_at,lang_pair',
            'sort_order' => 'sometimes|in:asc,desc',
        ];
    }
}
