<?php

namespace App\Http\Requests\API;

use App\Models\CatToolTmKey;
use App\Rules\SubProjectExistsRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'sub_project_id',
            'key',
            'is_writable',
        ],
        properties: [
            new OA\Property(property: 'sub_project_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'key', type: 'string'),
            new OA\Property(property: 'is_writable', type: 'boolean'),
        ]
    )
)]
class CatToolTmCreateRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'sub_project_id' => [
                'required',
                'uuid',
                new SubProjectExistsRule,
            ],
            'key' => ['required', 'string'],
            'is_writable' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $exists = CatToolTmKey::where('key', $this->validated('key'))
                    ->where('sub_project_id', $this->validated('sub_project_id'))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('key', 'The TM key is already exists for specified sub-project.');
                }
            }
        ];
    }
}
