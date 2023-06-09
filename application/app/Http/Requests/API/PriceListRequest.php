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
            //
            // TODO: add skill
            //
            'vendor_id' => 'sometimes|uuid',
            'src_lang_classifier_value_id' => 'sometimes|uuid',
            'dst_lang_classifier_value_id' => 'sometimes|uuid|different:src_lang_classifier_value_id',
            'institution_user_name' => 'sometimes|string',
            'order_by' => 'sometimes|in:character_fee,word_fee,page_fee,minute_fee,hour_fee,minimal_fee',
            'order_direction' => 'sometimes|in:asc,desc',
        ];
    }
}
