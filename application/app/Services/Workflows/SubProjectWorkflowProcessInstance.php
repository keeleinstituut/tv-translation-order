<?php

namespace App\Services\Workflows;

use App\Enums\JobKey;
use App\Jobs\Workflows\TrackSubProjectStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\Project;
use App\Models\SubProject;
use App\Services\Workflows\Tasks\TasksSearchResult;
use App\Services\Workflows\Templates\SubProjectWorkflowTemplateInterface;
use DomainException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

readonly class SubProjectWorkflowProcessInstance
{
    private Project $project;

    public function __construct(private SubProject $subProject, ?Project $project = null)
    {
        if (filled($project) && $this->subProject->project_id !== $project->id) {
            throw new InvalidArgumentException('Wrong project passed');
        }

        $this->project = $project ?: $this->subProject->project;
    }

    /**
     * @return void
     * @throws Throwable
     */
    public function start(): void
    {
        try {
            $this->storeWorkflowInstanceRef();

            $this->syncVariables();

            WorkflowService::sendMessage([
                'messageName' => 'SubProjectWorkflowStarted',
                'businessKey' => $this->getBusinessKey(),
                'correlationKeys' => [
                    'sub_project_id' => [
                        'value' => $this->subProject->id,
                        'type' => 'String'
                    ]
                ]
            ]);

            $this->subProject->workflow_started = true;
            $this->subProject->saveOrFail();

            TrackSubProjectStatus::dispatchSync($this->subProject);
        } catch (RequestException $e) {
            throw new RuntimeException("Starting of the sub-project workflow failed", $e->response->status(), $e);
        }
    }

    public function isStarted(): bool
    {
        return $this->subProject->workflow_started;
    }

    public function getTasksSearchResult(array $params = []): TasksSearchResult
    {
        return $this->project->workflow()->getTasksSearchResult(
            array_merge_recursive($params, [
                'processInstanceId' => $this->getId()
            ])
        );
    }

    public function getTaskDataBasedOnAssignment(Assignment $assignment): ?array
    {
        $searchResult = $this->getTasksSearchResult([
            'processVariables' => [
                [
                    'name' => 'assignment_id',
                    'value' => $assignment->id,
                    'operator' => 'eq'
                ]
            ]
        ]);

        if ($searchResult->getCount() === 0) {
            return null;
        }

        /**
         * Filtering based on the processVariables doesn't work properly
         * as all user tasks for a `job_key` will have all assignment_id's as process variables
         * Possible improvement: Mark task input variables as task local variables.
         */
        if ($searchResult->getCount() > 1) {
            $tasks = $searchResult->getTasks()->filter(
                fn(array $taskData) => data_get($taskData, 'variables.assignment_id') === $assignment->id
            );

            if ($tasks->count() === 1) {
                return $tasks->first();
            }

            throw new DomainException('Workflow contains multiple tasks for the assignment');
        }

        return $searchResult->getTasks()->get(0);
    }

    /**
     * @throws RequestException
     */
    public function syncVariables(): void
    {
        WorkflowService::updateProcessInstanceVariable(
            $this->getId(),
            'subProject',
            ['value' => $this->composeVariables()]
        );
    }

    public function composeVariables(): array
    {
        $template = $this->getWorkflowTemplate();

        return [
            'workflow_definition_id' => $template->getWorkflowProcessDefinitionId(),
            'sub_project_id' => $this->subProject->id,
            ...$this->subProject->assignments->groupBy(
                fn(Assignment $assignment) => $assignment->jobDefinition->job_key->value
            )->flatMap(function (Collection $assignments, string $jobKeyValue) {
                $jobKey = JobKey::from($jobKeyValue);

                $variableName = match ($jobKey) {
                    JobKey::JOB_TRANSLATION => 'translations',
                    JobKey::JOB_REVISION => 'revisions',
                    JobKey::JOB_OVERVIEW => 'overview'
                };

                if ($jobKey === JobKey::JOB_OVERVIEW) {
                    /** @var Assignment $assignment */
                    $assignment = $assignments->first();
                    return [
                        'overview' => [
                            'sub_project_id' => $this->subProject->id,
                            'institution_id' => $this->project->institution_id,
                            'assignee' => $assignment->subProject->project->manager_institution_user_id,
                            'candidateUsers' => [],
                            'assignment_id' => $assignment->id,
                            'source_language_classifier_value_id' => $this->subProject->source_language_classifier_value_id,
                            'destination_language_classifier_value_id' => $this->subProject->destination_language_classifier_value_id,
                            'type_classifier_value_id' => $this->project->type_classifier_value_id,
                            'deadline_at' => $assignment->deadline_at?->format(WorkflowService::DATETIME_FORMAT)
                        ]
                    ];
                }

                return [
                    $variableName => $assignments->map(function (Assignment $assignment) {
                        return [
                            'sub_project_id' => $this->subProject->id,
                            'institution_id' => $this->project->institution_id,
                            'assignee' => $assignment->assignee?->institution_user_id,
                            'candidateUsers' => $assignment->candidates->map(function (Candidate $candidate) {
                                return $candidate->vendor?->institution_user_id;
                            })->filter()->values()->toArray(),
                            'assignment_id' => $assignment->id,
                            'source_language_classifier_value_id' => $this->subProject->source_language_classifier_value_id,
                            'destination_language_classifier_value_id' => $this->subProject->destination_language_classifier_value_id,
                            'type_classifier_value_id' => $this->project->type_classifier_value_id,
                            'deadline_at' => $assignment->deadline_at?->format(WorkflowService::DATETIME_FORMAT)
                        ];
                    })->toArray()
                ];
            })->toArray()
        ];
    }

    private function getId(): string
    {
        if (empty($this->subProject->workflow_instance_ref)) {
            throw new RuntimeException('Sub-project workflow instance not defined');
        }

        return $this->subProject->workflow_instance_ref;
    }

    private function getBusinessKey(): string
    {
        return $this->project->workflow()->getBusinessKey();
    }

    private function getWorkflowTemplate(): SubProjectWorkflowTemplateInterface
    {
        return SubProjectWorkflowTemplatePicker::getWorkflowTemplate(
            $this->project->typeClassifierValue
                ->projectTypeConfig
                ->workflow_process_definition_id
        );
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    public function storeWorkflowInstanceRef(): void
    {
        if (filled($this->subProject->workflow_instance_ref)) {
            return;
        }

        $processInstances = WorkflowService::getProcessInstances([
            'businessKey' => $this->getBusinessKey(),
            'variables' => [
                [
                    'name' => 'sub_project_id',
                    'operator' => 'eq',
                    'value' => $this->subProject->id
                ]
            ]
        ]);

        if (count($processInstances) !== 1) {
            throw new RuntimeException('Sub-project workflow process instance not found');
        }

        $this->subProject->workflow_instance_ref = data_get($processInstances[0], 'id');
        $this->subProject->saveOrFail();
    }
}
