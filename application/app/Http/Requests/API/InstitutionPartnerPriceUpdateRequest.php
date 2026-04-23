<?php

namespace App\Http\Requests\API;

use App\Models\InstitutionPartnerPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InstitutionPartnerPriceUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $feeRule = 'sometimes|decimal:0,3|between:0,99999999.99';

        return [
            'id' => [
                'required',
                'uuid',
                Rule::exists(InstitutionPartnerPrice::class, 'id'),
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
                if ($validator->errors()->has('id')) {
                    return;
                }

                $belongsToInstitution = InstitutionPartnerPrice::query()
                    ->where('id', $this->id)
                    ->whereRelation('institutionPartner', 'institution_id', Auth::user()->institutionId)
                    ->exists();

                if (! $belongsToInstitution) {
                    $validator->errors()->add('id', 'The selected id is invalid.');
                }
            },
        ];
    }
}
