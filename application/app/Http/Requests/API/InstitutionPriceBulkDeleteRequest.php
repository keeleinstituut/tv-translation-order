<?php

namespace App\Http\Requests\API;

use App\Models\InstitutionPrice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['id'],
        properties: [
            new OA\Property(
                property: 'id',
                type: 'array',
                items: new OA\Items(type: 'string', format: 'uuid'),
                minItems: 1
            ),
        ]
    )
)]
class InstitutionPriceBulkDeleteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required|array|min:1',
            'id.*' => [
                'uuid',
                'distinct',
                Rule::exists(InstitutionPrice::class, 'id')
                    ->where('institution_id', Auth::user()->institutionId),
            ],
        ];
    }
}
