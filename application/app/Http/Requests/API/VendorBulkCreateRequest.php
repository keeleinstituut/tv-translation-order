<?php

namespace App\Http\Requests\API;

use App\Models\CachedEntities\InstitutionUser;
use App\Models\Vendor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
                    required: ['institution_user_id', 'company_name'],
                    properties: [
                        new OA\Property(property: 'institution_user_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'company_name', type: 'string'),
                    ],
                    type: 'object'
                ),
                minItems: 1
            ),
        ]
    )
)]
class VendorBulkCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'data' => 'required|array|min:1',
            'data.*.institution_user_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists(InstitutionUser::class, 'id'),
                Rule::unique(Vendor::class, 'institution_user_id'),
            ],
            'data.*.company_name' => [
                'string',
            ],
        ];
    }
}
