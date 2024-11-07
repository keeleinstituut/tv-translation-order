<?php

namespace App\Http\Requests\API;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\Price;
use App\Models\Skill;
use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'vendor_id', 'skill_id', 'src_lang_classifier_value_id', 'dst_lang_classifier_value_id',
            'character_fee', 'word_fee', 'page_fee', 'minute_fee', 'hour_fee',
            'minimal_fee'],
        properties: [
            new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'skill_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'src_lang_classifier_value_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'dst_lang_classifier_value_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'character_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'word_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'page_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'minute_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'hour_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'minimal_fee', type: 'number', format: 'double'),
        ]
    )
)]
class PriceCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $feeRule = 'required|decimal:0,3|between:0,99999999.99';
        return [
            'vendor_id' => [
                'required',
                'uuid',
                Rule::exists(Vendor::class, 'id'),
            ],
            'skill_id' => [
                'required',
                'uuid',
                Rule::exists(Skill::class, 'id'),
            ],
            'src_lang_classifier_value_id' => [
                'required',
                'uuid',
                Rule::exists(ClassifierValue::class, 'id')->where('type', 'LANGUAGE'),
            ],
            'dst_lang_classifier_value_id' => [
                'required',
                'uuid',
                'different:src_lang_classifier_value_id',
                Rule::exists(ClassifierValue::class, 'id')->where('type', 'LANGUAGE'),
            ],
            'character_fee' => $feeRule,
            'word_fee' => $feeRule,
            'page_fee' => $feeRule,
            'minute_fee' => $feeRule,
            'hour_fee' => $feeRule,
            'minimal_fee' => $feeRule,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $existing = Price::getModel()
                    ->where('vendor_id', $this->vendor_id)
                    ->where('skill_id', $this->skill_id)
                    ->where('src_lang_classifier_value_id', $this->src_lang_classifier_value_id)
                    ->where('dst_lang_classifier_value_id', $this->dst_lang_classifier_value_id)
                    ->get();

                if ($existing->isNotEmpty()) {
                    $msg = 'Price already exists';
                    $validator->errors()
                        ->add('vendor_id', $msg)
                        ->add('skill_id', $msg)
                        ->add('src_lang_classifier_value_id', $msg)
                        ->add('dst_lang_classifier_value_id', $msg);
                }
            },
        ];
    }
}
