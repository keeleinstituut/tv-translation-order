<?php

namespace App\Services\Workflows;

use App\Enums\JobKey;
use App\Jobs\TrackSubProjectStatus;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;
use App\Services\Workflows\Tasks\TasksSearchResult;
use App\Services\Workflows\Tasks\WorkflowTasksDataProvider;
use App\Services\Workflows\Templates\SubProjectWorkflowTemplateInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use InvalidArgumentException;
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
        $variables = $this->composeProcessInstanceVariables();

        if (empty($variables['subProjects'])) {
            throw new InvalidArgumentException("Trying to start workflow for the project without sub-projects");
        }

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

            $subProject->workflow_started = true;
            $subProject->saveOrFail();

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
     * @throws RequestException
     */
    public function updateProcessInstanceVariable(string $name, $value)
    {
        WorkflowService::updateProcessInstanceVariable(
            $this->getProcessInstanceId(),
            $name,
            $value
        );
    }


    /**
     * @throws Throwable
     */
    public function cancel(): void
    {
        WorkflowService::deleteProcessInstances([
            $this->getProcessInstanceId()
        ], 'Project cancelled');

        $this->project->workflow_instance_ref = null;
        $this->project->saveOrFail();
    }

    public function getTasksSearchResult(array $params = []): TasksSearchResult
    {
        return (new WorkflowTasksDataProvider)->search([
            ...$params,
            'processInstanceBusinessKey' => $this->getBusinessKey()
        ]);
    }

    public function isStarted(): bool
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
            'project_id' => [
                'value' => $this->project->id,
            ],
            'client_institution_user_id' => [
                'value' => $this->project->client_institution_user_id
            ],
            'manager_institution_user_id' => [
                'value' => $this->project->manager_institution_user_id
            ],
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
                                        'sub_project_id' => $subProject->id,
                                        'institution_id' => $this->project->institution_id,
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
                                        'sub_project_id' => $subProject->id,
                                        'institution_id' => $this->project->institution_id,
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
