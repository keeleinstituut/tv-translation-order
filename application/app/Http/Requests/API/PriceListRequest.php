<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class PriceListRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'limit' => 'sometimes|integer|max:50',
            'skill_id' => 'sometimes|array',
            'skill_id.*' => 'uuid',
            'vendor_id' => 'sometimes|uuid',
            'src_lang_classifier_value_id' => 'sometimes|array',
            'src_lang_classifier_value_id.*' => 'uuid',
            'dst_lang_classifier_value_id' => 'sometimes|array',
            'dst_lang_classifier_value_id.*' => 'uuid',
            'lang_pair' => 'sometimes|array',
            'lang_pair.*.src' => 'required|uuid',
            'lang_pair.*.dst' => 'required|uuid',
            'institution_user_name' => 'sometimes|string',
            'sort_by' => 'sometimes|in:character_fee,word_fee,page_fee,minute_fee,hour_fee,minimal_fee,created_at',
            'sort_order' => 'sometimes|in:asc,desc',
        ];
    }
}
