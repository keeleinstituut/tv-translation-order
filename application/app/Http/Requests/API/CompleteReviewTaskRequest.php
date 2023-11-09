<?php

namespace App\Http\Requests\API;

use App\Models\Media;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'accepted',
        ],
        properties: [
            new OA\Property(property: 'accepted', type: 'boolean'),
            new OA\Property(property: 'final_file_id', type: 'array', items: new OA\Items(type: 'integer')),
        ]
    )
)]
class CompleteReviewTaskRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'accepted' => ['required', 'boolean'],
            'final_file_id' => ['required_if:accepted,1', 'array'],
            'final_file_id.*' => [
                'required',
                'integer',
                Rule::exists(Media::class, 'id'),
            ],
        ];
    }
}
