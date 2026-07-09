<?php

namespace App\Http\Requests\API;

use App\Models\InstitutionPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InstitutionPriceUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $feeRule = 'sometimes|decimal:0,3|between:0,99999999.99';

        return [
            'id' => [
                'required',
                'uuid',
                Rule::exists(InstitutionPrice::class, 'id')
                    ->where('institution_id', Auth::user()->institutionId),
            ],
            'character_fee' => $feeRule,
            'word_fee' => $feeRule,
            'page_fee' => $feeRule,
            'minute_fee' => $feeRule,
            'hour_fee' => $feeRule,
            'minimal_fee' => $feeRule,
        ];
    }
}
