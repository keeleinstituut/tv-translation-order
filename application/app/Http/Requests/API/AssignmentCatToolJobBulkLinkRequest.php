<?php

namespace App\Http\Requests\API;

use App\Enums\JobKey;
use App\Models\Assignment;
use App\Models\CatToolJob;
use App\Models\JobDefinition;
use App\Models\SubProject;
use App\Policies\AssignmentPolicy;
use App\Policies\CatToolJobPolicy;
use App\Policies\SubProjectPolicy;
use App\Rules\SubProjectExistsRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'linking',
            'sub_project_id',
            'feature',
        ],
        properties: [
            new OA\Property(property: 'linking', type: 'array', items: new OA\Items(
                required: ['cat_tool_job_id', 'assignment_id'],
                properties: [
                    new OA\Property(property: 'cat_tool_job_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid'),
                ],
                type: 'object'
            )),
            new OA\Property(property: 'job_key', type: 'string', enum: JobKey::class),
            new OA\Property(property: 'sub_project_id', type: 'string', format: 'uuid'),
        ]
    )
)]
class AssignmentCatToolJobBulkLinkRequest extends FormRequest
{
    private ?Collection $catToolJobs = null;

    private ?Collection $assignments = null;

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
            'linking' => ['present', 'array'],
            'linking.*.assignment_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) {
                    $exists = Assignment::withGlobalScope('policy', AssignmentPolicy::scope())
                        ->where('id', $value)->exists();

                    if (! $exists) {
                        $fail('The assignment with such ID does not exist.');
                    }
                },
            ],
            'linking.*.cat_tool_job_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) {
                    $exists = CatToolJob::withGlobalScope('policy', CatToolJobPolicy::scope())
                        ->where('id', $value)->exists();

                    if (! $exists) {
                        $fail('The XLIFF with such ID does not exist.');
                    }
                },
            ],
            'sub_project_id' => [
                'required',
                'uuid',
                new SubProjectExistsRule,
            ],
            'job_key' => [
                'required',
                'string',
                new Enum(JobKey::class),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (empty($this->validated('linking'))) {
                    return;
                }

                $this->collect($this->validated('linking'))->each(
                    function (array $linking, int $idx) use ($validator) {
                        $catToolJobHasWrongSubProject = $this->getCatToolJobs()->get($linking['cat_tool_job_id'])
                            ?->sub_project_id !== $this->validated('sub_project_id');

                        $validator->errors()->addIf(
                            $catToolJobHasWrongSubProject,
                            'linking.'.$idx.'.cat_tool_job_id',
                            'XLIFF file belongs to another sub-project'
                        );

                        $assignmentHasWrongSubProject = $this->getAssignments()->get($linking['assignment_id'])
                            ?->sub_project_id !== $this->validated('sub_project_id');

                        $validator->errors()->addIf(
                            $assignmentHasWrongSubProject,
                            'linking.'.$idx.'.assignment_id',
                            'Assignment belongs to another sub-project'
                        );

                        $assignmentHasWrongFeature = $this->getAssignments()->get($linking['assignment_id'])
                            ?->job_definition_id !== $this->getJobDefinition()->id;

                        $validator->errors()->addIf(
                            $assignmentHasWrongFeature,
                            'linking.'.$idx.'.assignment_id',
                            'Assignment belongs to another job'
                        );
                    }
                );
            },
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (empty($jobDefinition = $this->getJobDefinition())) {
                    $validator->errors()->add(
                        'job_key',
                        'The project doesn\'t contain '.$this->validated('job_key')
                    );

                    return;
                }

                $validator->errors()->addIf(
                    ! $jobDefinition->linking_with_cat_tool_jobs_enabled,
                    'job_key',
                    'The linking is not available for the job '.$this->validated('job_key')
                );
            },
        ];
    }

    /**
     * @return Collection<string, CatToolJob>
     */
    public function getCatToolJobs(): Collection
    {
        if ($this->catToolJobs !== null) {
            return $this->catToolJobs;
        }

        return $this->catToolJobs = CatToolJob::whereIn('id', $this->validated('linking.*.cat_tool_job_id'))
            ->get()->keyBy('id');
    }

    /**
     * @return Collection<string, Assignment>
     */
    public function getAssignments(): Collection
    {
        if ($this->assignments !== null) {
            return $this->assignments;
        }

        return $this->assignments = Assignment::withGlobalScope('policy', AssignmentPolicy::scope())
            ->whereIn('id', $this->validated('linking.*.assignment_id'))
            ->get()->keyBy('id');
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
