<?php

namespace App\Http\Requests\API;

use App\Models\CachedEntities\Institution;
use App\Models\InstitutionPartner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['partner_institution_id'],
        properties: [
            new OA\Property(property: 'partner_institution_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'discount_percentage_101', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_repetitions', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_100', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_95_99', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_85_94', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_75_84', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_50_74', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_0_49', type: 'number', format: 'double', nullable: true),
        ]
    )
)]
class InstitutionPartnerCreateRequest extends FormRequest
{
    public function rules(): array
    {
        $discountRule = 'sometimes|nullable|decimal:0,2|between:0,100.00';

        return [
            'partner_institution_id' => [
                'required',
                'uuid',
                Rule::exists(Institution::class, 'id'),
            ],
            'discount_percentage_101' => $discountRule,
            'discount_percentage_repetitions' => $discountRule,
            'discount_percentage_100' => $discountRule,
            'discount_percentage_95_99' => $discountRule,
            'discount_percentage_85_94' => $discountRule,
            'discount_percentage_75_84' => $discountRule,
            'discount_percentage_50_74' => $discountRule,
            'discount_percentage_0_49' => $discountRule,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->partner_institution_id === Auth::user()->institutionId) {
                    $validator->errors()->add('partner_institution_id', 'An institution cannot partner with itself');
                }

                $existing = InstitutionPartner::query()
                    ->where('institution_id', Auth::user()->institutionId)
                    ->where('partner_institution_id', $this->partner_institution_id)
                    ->exists();

                if ($existing) {
                    $validator->errors()->add('partner_institution_id', 'A partner relationship already exists with this institution');
                }
            },
        ];
    }
}
