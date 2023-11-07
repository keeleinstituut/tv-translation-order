<?php

namespace App\Services\Workflows;

use App\Enums\AssignmentStatus;
use App\Enums\Feature;
use App\Enums\JobKey;
use App\Enums\ProjectStatus;
use App\Enums\SubProjectStatus;
use App\Jobs\TrackSubProjectStatus;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\Project;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Policies\AssignmentPolicy;
use App\Services\Workflows\Templates\SubProjectWorkflowTemplateInterface;
use DB;
use DomainException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

class WorkflowProcessInstanceService
{
    private Project $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    public function startWorkflowProcessInstance(): void
    {
        $response = WorkflowService::startProcessDefinition($this->getProcessDefinitionId(), [
            'businessKey' => $this->getBusinessKey(),
            'variables' => $this->composeProcessInstanceVariables()
        ]);

        if (!isset($response['id'])) {
            throw new RuntimeException("Camunda responded with unexpected response body format");
        }

        $this->project->workflow_instance_ref = data_get($response, 'id');
        $this->project->saveOrFail();
    }

    /**
     * @throws Throwable
     */
    public function startSubProjectWorkflow(SubProject $subProject): void
    {
        try {
            WorkflowService::sendMessage([
                'messageName' => 'SubProjectWorkflowStarted',
                'businessKey' => $this->getBusinessKey(),
                'correlationKeys' => [
                    'sub_project_id' => [
                        'value' => $subProject->id,
                        'type' => 'String'
                    ]
                ]
            ]);

            TrackSubProjectStatus::dispatch($subProject);
        } catch (RequestException $e) {
            throw new RuntimeException("Starting of the sub-project workflow failed", $e->response->status(), $e);
        }
    }

    public function syncProcessInstanceVariables(): void
    {
        $variables = $this->composeProcessInstanceVariables();
        try {
            foreach ($variables as $name => $value) {
                WorkflowService::updateProcessInstanceVariable(
                    $this->getProcessInstanceId(),
                    $name,
                    $value
                );
            }
        } catch (Throwable $e) {
            throw new RuntimeException('Updating of the process instance variables failed.', previous: $e);
        }
    }

    /**
     * @throws Throwable
     */
    public function markTaskAsCompletedBasedOnAssignment(Assignment $assignment): void
    {
        $taskId = $this->retrieveTaskIdBasedOnAssignment($assignment);
        if (empty($taskId)) {
            $assignment->status = AssignmentStatus::Done;
            $assignment->saveOrFail();
            $this->syncProcessInstanceVariables();
        }

        $this->markAssignmentTaskAsCompleted($taskId, $assignment);
    }

    /**
     * @throws Throwable
     */
    public function markTaskAsCompleted(string $taskId): void
    {
        if (filled($assignment = $this->retrieveAssignmentBasedOnTaskId($taskId))) {
            $this->markAssignmentTaskAsCompleted($taskId, $assignment);
            return;
        }
    }

    private function retrieveTaskIdBasedOnAssignment(Assignment $assignment): ?string
    {
        $tasks = $this->getTasks();
        foreach ($tasks as $task) {
            if (filled($task['assignment_id']) && $task['assignment_id'] === $assignment->id) {
                return $task['id'] ?? null;
            }
        }

        return null;
    }

    /**
     * @throws Throwable
     */
    private function markAssignmentTaskAsCompleted(string $taskId, Assignment $assignment, array $params = []): void
    {
        match ($assignment->jobDefinition->job_key) {
            JobKey::JOB_OVERVIEW => self::completeSubProjectReviewTask(
                $taskId, data_get($params, 'is_successful')
            ),
            default => self::completeSimpleTask($taskId)
        };

        $assignment->status = AssignmentStatus::Done;
        $assignment->saveOrFail();

        TrackSubProjectStatus::dispatch($assignment->subProject);
    }


    private function retrieveAssignmentBasedOnTaskId(string $taskId): ?Assignment
    {
        $tasks = $this->getTasks();
        foreach ($tasks as $task) {
            if (filled($task['assignment_id'])) {
                return Assignment::find($task['assignment_id']);
            }
        }

        return null;
    }

    private function retrieveTaskData(string $taskId): array
    {
        return WorkflowService::getTasks(['id' => $taskId]);
    }

    private function completeSimpleTask(string $taskId): void
    {
        try {
            WorkflowService::completeTask($taskId);
        } catch (RequestException $e) {
            throw new RuntimeException("Marking task as completed failed", $e->getCode(), previous: $e);
        }
    }

    private function completeSubProjectReviewTask(string $taskId, bool $successful = true): void
    {
        try {
            WorkflowService::completeTask($taskId, [
                'variables' => [
                    'subProjectFinished' => [
                        'value' => $successful,
                    ]
                ]
            ]);
        } catch (RequestException $e) {
            throw new RuntimeException("Marking sub-project review task as completed failed", $e->getCode(), previous: $e);
        }
    }

    /**
     * @throws Throwable
     */
    private function completeProjectReviewTask(string $taskId, bool $acceptedByClient): void
    {
        WorkflowService::completeTask($taskId, [
            'variables' => [
                'acceptedByClient' => [
                    'value' => $acceptedByClient,
                ]
            ]
        ]);

        $this->project->status = $acceptedByClient ? ProjectStatus::Accepted :
            ProjectStatus::Rejected;
        $this->project->saveOrFail();
    }

    /**
     * TODO: implement getting of the sub-project ID based on the task ID.
     * @param string $taskId
     * @return SubProject
     */
    private function getSubProject(string $taskId): SubProject
    {
        return $this->project->subProjects()->first();
    }

    public function cancel(): void
    {
        WorkflowService::deleteProcessInstances([
            $this->getProcessInstanceId()
        ], 'Project cancelled');
    }

    public function getTasks()
    {
        return WorkflowService::getTasks([
            'processInstanceId' => $this->getProcessInstanceId(),
        ]);
    }

    public function isStarted(): string
    {
        return filled($this->project->workflow_instance_ref);
    }

    private function getProcessDefinitionId(): ?string
    {
        return $this->project->workflow_template_id;
    }

    private function getProcessInstanceId(): ?string
    {
        return $this->project->workflow_instance_ref;
    }

    private function getBusinessKey(): string
    {
        return 'workflow.' . $this->project->id;
    }

    private function composeProcessInstanceVariables(): array
    {
        $template = $this->getSubProjectWorkflowTemplate();

        return [
            'subProjects' => [
                'value' => $this->project->subProjects->map(function (SubProject $subProject) use ($template) {
                    return [
                        'workflow_definition_id' => $template->getWorkflowProcessDefinitionId(),
                        'sub_project_id' => $subProject->id,
                        ...$subProject->assignments->groupBy(
                            fn(Assignment $assignment) => $assignment->jobDefinition->job_key->value
                        )->flatMap(function (Collection $assignments, string $jobKeyValue) use ($subProject) {
                            $jobKey = JobKey::from($jobKeyValue);

                            $variableName = match ($jobKey) {
                                JobKey::JOB_TRANSLATION => 'translations',
                                JobKey::JOB_REVISION => 'revisions',
                                JobKey::JOB_OVERVIEW => 'overview'
                            };

                            if ($jobKey === JobKey::JOB_OVERVIEW) {
                                /** @var Assignment $assignment */
                                $assignment = $assignments->first();
                                $deadline = ($assignment->deadline_at ?: $subProject->deadline_at) ?: $this->project->deadline_at;
                                return [
                                    'overview' => [
                                        'institution_id' => $assignment->subProject->project->institution_id,
                                        'assignee' => $assignment->assigned_vendor_id,
                                        'candidateUsers' => $assignment->candidates->pluck('vendor_id')->toArray(),
                                        'assignment_id' => $assignment->id,
                                        'source_language_classifier_value_id' => $subProject->source_language_classifier_value_id,
                                        'destination_language_classifier_value_id' => $subProject->destination_language_classifier_value_id,
                                        'type_classifier_value_id' => $subProject->project->type_classifier_value_id,
                                        'deadline_at' => filled($deadline) ? $deadline->toString() : null
                                    ]
                                ];
                            }

                            return [
                                $variableName => $assignments->map(function (Assignment $assignment) use ($subProject) {
                                    $deadline = ($assignment->deadline_at ?: $subProject->deadline_at) ?: $this->project->deadline_at;
                                    return [
                                        'institution_id' => $assignment->subProject->project->institution_id,
                                        'assignee' => $assignment->assigned_vendor_id,
                                        'candidateUsers' => $assignment->candidates->pluck('vendor_id')->toArray(),
                                        'assignment_id' => $assignment->id,
                                        'source_language_classifier_value_id' => $subProject->source_language_classifier_value_id,
                                        'destination_language_classifier_value_id' => $subProject->destination_language_classifier_value_id,
                                        'type_classifier_value_id' => $subProject->project->type_classifier_value_id,
                                        'deadline_at' => filled($deadline) ? $deadline->toString() : null
                                    ];
                                })->toArray()
                            ];
                        })->toArray()
                    ];
                })->toArray()
            ]
        ];
    }

    private function getSubProjectWorkflowTemplate(): SubProjectWorkflowTemplateInterface
    {
        return SubProjectWorkflowTemplatePicker::getWorkflowTemplate(
            $this->project->typeClassifierValue
                ->projectTypeConfig
                ->workflow_process_definition_id
        );
    }
}
