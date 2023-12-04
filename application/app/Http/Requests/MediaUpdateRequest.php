<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['help_file_type'],
        properties: [
            new OA\Property(property: 'help_file_type', type: 'string', enum: Project::HELP_FILE_TYPES),
        ]
    )
)]
class MediaUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'help_file_type' => ['required', Rule::in(Project::HELP_FILE_TYPES)]
        ];
    }
}
