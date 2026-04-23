<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class VendorSkillLanguagePairListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'per_page' => 'sometimes|integer|max:50',
            'vendor_id' => 'sometimes|array',
            'vendor_id.*' => 'uuid',
            'skill_id' => 'sometimes|array',
            'skill_id.*' => 'uuid',
            'src_lang_classifier_value_id' => 'sometimes|array',
            'src_lang_classifier_value_id.*' => 'uuid',
            'dst_lang_classifier_value_id' => 'sometimes|array',
            'dst_lang_classifier_value_id.*' => 'uuid',
            'lang_pair' => 'sometimes|array',
            'lang_pair.*.src' => 'required|uuid',
            'lang_pair.*.dst' => 'required|uuid',
            'sort_by' => 'sometimes|in:created_at,lang_pair',
            'sort_order' => 'sometimes|in:asc,desc',
        ];
    }
}
