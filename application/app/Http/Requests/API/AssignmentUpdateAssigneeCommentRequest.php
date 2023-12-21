<?php

namespace App\Http\Requests\API;

use App\Http\Requests\Helpers\MaxLengthValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'assignee_comments', type: 'string'),
        ]
    )
)]
class AssignmentUpdateAssigneeCommentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'assignee_comments' => ['required', 'string', 'max:'. MaxLengthValue::TEXT],
        ];
    }
}
