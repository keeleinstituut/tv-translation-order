<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Models\Vendor;
use App\Models\ClassifierValue;
use App\Models\Price;

class PriceCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        // dd($this->validationData());
        return [
            'vendor_id' => [
                'required',
                'uuid',
                Rule::exists(Vendor::class, 'id')
            ],
            //
            // TODO: add skill
            //
            'src_lang_classifier_value_id' => [
                'required',
                'uuid',
                Rule::exists(ClassifierValue::class, 'id')->where('type', 'LANGUAGE'),
            ],
            'dst_lang_classifier_value_id' => [
                'required',
                'uuid',
                'different:src_lang_classifier_value_id',
                Rule::exists(ClassifierValue::class, 'id')->where('type', 'LANGUAGE')
            ],
            'character_fee' => 'required|decimal:0,2|between:0,99999999.99',
            'word_fee' => 'required|decimal:0,2|between:0,99999999.99',
            'page_fee' => 'required|decimal:0,2|between:0,99999999.99',
            'minute_fee' => 'required|decimal:0,2|between:0,99999999.99',
            'hour_fee' => 'required|decimal:0,2|between:0,99999999.99',
            'minimal_fee' => 'required|decimal:0,2|between:0,99999999.99',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $existing = Price::getModel()
                    ->where('vendor_id', $this->vendor_id)
                    ->where('src_lang_classifier_value_id', $this->src_lang_classifier_value_id)
                    ->where('dst_lang_classifier_value_id', $this->dst_lang_classifier_value_id)
                    ->get();

                if ($existing->isNotEmpty()) {
                    $msg = 'Price already exists';
                    $validator->errors()
                        ->add('vendor_id', $msg)
                        ->add('src_lang_classifier_value_id', $msg)
                        ->add('dst_lang_classifier_value_id', $msg);
                }
            }
        ];
    }
}
