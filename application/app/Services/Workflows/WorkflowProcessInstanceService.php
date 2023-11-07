<?php

namespace App\Services\Workflows;

use App\Enums\Feature;
use App\Enums\JobKey;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;
use App\Services\Workflows\Templates\SubProjectWorkflowTemplateInterface;
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
        $template = SubProjectWorkflowTemplatePicker::getWorkflowTemplate(
            $this->project->typeClassifierValue
                ->projectTypeConfig
                ->workflow_process_definition_id
        );

        $response = WorkflowService::startProcessDefinition($this->getProcessDefinitionId(), [
            'businessKey' => $this->getBusinessKey(),
            'variables' => $this->composeProcessInstanceVariables($template)
        ]);

        if (!isset($response['id'])) {
            throw new RuntimeException("Camunda responded with unexpected response body format");
        }

        $this->project->workflow_instance_ref = data_get($response, 'id');
        $this->project->saveOrFail();
    }

    public function triggerSubProjectWorkflowStart(SubProject $subProject): void
    {
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
    }

    public function completeSubProjectReviewTask(string $taskId, bool $successful = true): void
    {
        WorkflowService::completeTask($taskId, [
            'variables' => [
                'subProjectFinished' => [
                    'value' => $successful,
                ]
            ]
        ]);
    }

    public function completeProjectReviewTask(string $taskId, bool $successful = true): void
    {
        WorkflowService::completeTask($taskId, [
            'variables' => [
                "acceptedByClient" => [
                    'value' => $successful,
                ]
            ]
        ]);
    }

    public function cancelProjectWorkflow(): void
    {
        WorkflowService::deleteProcessInstances([
            $this->getProcessInstanceId()
        ], 'Project cancelled');
    }

    public function getTasks()
    {
        return WorkflowService::getTask([
            'processInstanceId' => $this->getProcessInstanceId(),
        ]);
    }

    public function updateProcessVariable($variableName, $newValue)
    {
        return WorkflowService::updateProcessInstanceVariable($this->getProcessInstanceId(), $variableName, $newValue);
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

    private function composeProcessInstanceVariables(SubProjectWorkflowTemplateInterface $template): array
    {
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
}
