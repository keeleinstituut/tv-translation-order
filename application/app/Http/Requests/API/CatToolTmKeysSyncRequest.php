<?php

namespace App\Http\Requests\API;

use App\Enums\TagType;
use App\Models\CatToolTmKey;
use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use App\Rules\SubProjectExistsRule;
use App\Services\CatTools\Enums\CatToolSetupStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'sub_project_id',
            'tm_keys',
        ],
        properties: [
            new OA\Property(property: 'sub_project_id', type: 'string', format: 'uuid'),
            new OA\Property(
                property: 'tm_keys',
                type: 'array',
                items: new OA\Items(
                    required: ['key', 'is_writable'],
                    properties: [
                        new OA\Property(property: 'key', type: 'string'),
                        new OA\Property(property: 'is_writable', type: 'boolean'),
                    ],
                    type: 'object'
                ),
                minItems: 1
            )
        ]
    )
)]
class CatToolTmKeysSyncRequest extends FormRequest
{
    private ?SubProject $subProject;

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
            'tm_keys' => ['present', 'array', 'max:10'],
            'tm_keys.*.key' => ['required', 'string'],
            'tm_keys.*.is_writable' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $writeTmCount = array_sum($this->validated('tm_keys.*.is_writable'));
                if ($writeTmCount === 0) {
                    $validator->errors()->add('tm_keys', 'At least one TM should be writable');
                }

                if ($writeTmCount > 2) {
                    $validator->errors()->add('tm_keys', 'Not more than two translation memories can be writable');
                }

                $subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
                    ->findOrFail($this->validated('sub_project_id'));

                if ($subProject->cat()->getSetupStatus() === CatToolSetupStatus::Done) {
                    if (empty($this->validated('tm_keys'))) {
                        $validator->errors()->add('tm_keys', 'At least one TM should be added');
                    }
                }
            }
        ];
    }

    public function getSubProject(): SubProject
    {
        if (empty($this->subProject)) {
            $this->subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
                ->findOrFail($this->validated('sub_project_id'));
        }

        return $this->subProject;
    }
}
