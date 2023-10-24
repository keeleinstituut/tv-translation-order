<?php

namespace App\Http\Requests\API;

use App\Enums\Feature;
use App\Enums\JobKey;
use App\Models\JobDefinition;
use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use App\Rules\SubProjectExistsRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'sub_project_id',
            'job_key',
        ],
        properties: [
            new OA\Property(property: 'sub_project_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'job_key', type: 'string', enum: JobKey::class),
        ]
    )
)]
class AssignmentCreateRequest extends FormRequest
{

    private ?JobDefinition $jobDefinition = null;

    private ?SubProject $subProject = null;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'sub_project_id' => ['required', 'uuid', new SubProjectExistsRule],
            'job_key' => ['string', new Enum(JobKey::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
                    ->find($this->validated('sub_project_id'));

                /** @var JobDefinition $jobDefinition */
                $jobDefinition = $subProject->project->typeClassifierValue->projectTypeConfig
                    ->jobDefinitions()->where('job_key', $this->validated('job_key'))->first();

                if (empty($jobDefinition)) {
                    $validator->errors()->add(
                        'job_key',
                        'The project doesn\'t contain '.$this->validated('job_key')
                    );
                    return;
                }

                $validator->errors()->addIf(
                    !$jobDefinition->multi_assignments_enabled,
                    'job_key',
                    'Multi-assignments not available for the job'
                );
            },
        ];
    }

    public function getJobDefinition(): ?JobDefinition
    {
        if (is_null($this->jobDefinition)) {
            $this->jobDefinition = $this->getSubProject()?->project
                ->typeClassifierValue->projectTypeConfig
                ->jobDefinitions()->where('job_key', $this->validated('job_key'))
                ->first();
        }

        return $this->jobDefinition;
    }

    public function getSubProject(): ?SubProject
    {
        if (is_null($this->subProject)) {
            $this->subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
                ->find($this->validated('sub_project_id'));
        }

        return $this->subProject;
    }
}
