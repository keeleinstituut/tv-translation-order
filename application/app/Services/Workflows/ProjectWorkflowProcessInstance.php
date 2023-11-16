<?php

namespace App\Services\Workflows;

use App\Models\Project;
use App\Models\SubProject;
use App\Services\Workflows\Tasks\TasksSearchResult;
use App\Services\Workflows\Tasks\WorkflowTasksDataProvider;
use Illuminate\Http\Client\RequestException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

readonly class ProjectWorkflowProcessInstance
{
    public function __construct(private Project $project)
    {
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    public function start(): void
    {
        $variables = $this->composeVariables();

        if (empty($variables['subProjects'])) {
            throw new InvalidArgumentException("Trying to start workflow for the project without sub-projects");
        }

        $response = WorkflowService::startProcessDefinition($this->getProcessDefinitionId(), [
            'businessKey' => $this->getBusinessKey(),
            'variables' => $this->composeVariables()
        ]);

        if (!isset($response['id'])) {
            throw new RuntimeException("Camunda responded with unexpected response body format");
        }

        $this->project->workflow_instance_ref = data_get($response, 'id');
        $this->project->saveOrFail();
    }

    /**
     * @throws RequestException
     * @throws Throwable
     */
    public function restart(): void
    {
        if (!$this->isStarted()) {
            throw new RuntimeException('Not possible to restart not started workflow');
        }

        $this->cancel('Restart project workflow');
        $this->start();
    }

    /**
     * @throws RequestException
     */
    public function updateVariable(string $name, $value): void
    {
        WorkflowService::updateProcessInstanceVariable(
            $this->getId(),
            $name,
            ['value' => $value]
        );
    }


    /**
     * @throws Throwable
     */
    public function cancel(string $reason = ''): void
    {
        WorkflowService::deleteProcessInstances([
            $this->getId()
        ], $reason);
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

    private function getId(): ?string
    {
        return $this->project->workflow_instance_ref;
    }

    public function getBusinessKey(): string
    {
        return 'workflow.workflow' . $this->project->id;
    }

    private function composeVariables(): array
    {
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
            'institution_id' => [
                'value' => $this->project->institution_id,
            ],
            'type_classifier_value_id' => [
                'value' => $this->project->type_classifier_value_id,
            ],
            'deadline_at' => [
                'value' => $this->project->deadline_at->toIso8601String(),
            ],
            'subProjects' => [
                'value' => $this->project->subProjects->map(function (SubProject $subProject) {
                    return (new SubProjectWorkflowProcessInstance($subProject, $this->project))
                        ->composeVariables();
                })->toArray()
            ]
        ];
    }
}
