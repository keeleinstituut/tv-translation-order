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
        required: ['data'],
        properties: [
            new OA\Property(
                property: 'data',
                type: 'array',
                items: new OA\Items(
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
                    ],
                    type: 'object'
                ),
                minItems: 1
            ),
        ]
    )
)]
class InstitutionPartnerBulkCreateRequest extends FormRequest
{
    public function rules(): array
    {
        $discountRule = 'sometimes|nullable|decimal:0,2|between:0,100.00';

        return [
            'data' => 'required|array|min:1',
            'data.*.partner_institution_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists(Institution::class, 'id'),
                Rule::unique(InstitutionPartner::class, 'partner_institution_id')
                    ->where('institution_id', Auth::user()->institutionId)
                    ->whereNull('deleted_at'),
            ],
            'data.*.discount_percentage_101' => $discountRule,
            'data.*.discount_percentage_repetitions' => $discountRule,
            'data.*.discount_percentage_100' => $discountRule,
            'data.*.discount_percentage_95_99' => $discountRule,
            'data.*.discount_percentage_85_94' => $discountRule,
            'data.*.discount_percentage_75_84' => $discountRule,
            'data.*.discount_percentage_50_74' => $discountRule,
            'data.*.discount_percentage_0_49' => $discountRule,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                collect($this->input('data', []))->each(function (array $item, int $index) use ($validator) {
                    if (($item['partner_institution_id'] ?? null) === Auth::user()->institutionId) {
                        $validator->errors()->add(
                            "data.$index.partner_institution_id",
                            'An institution cannot partner with itself'
                        );
                    }
                });
            },
        ];
    }
}
