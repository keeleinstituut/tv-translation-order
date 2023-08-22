<?php

namespace App\Services\Workflows;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;

class WorkflowProcessInstanceService
{
    private Project $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
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

    public function startProcessInstance()
    {
        $template = SubProjectWorkflowTemplatePicker::getWorkflowTemplate(
            $this->project->typeClassifierValue->projectTypeConfig->workflow_process_definition_id
        );


        return WorkflowService::startProcessDefinitionInstance($this->getProcessDefinitionId(), [
            'businessKey' => $this->getBusinessKey(),
            'variables' => [
                'subProjects' => [
                    'value' => $template->getVariables($this->project)
                ]
            ]
        ]);
    }


    private function getProcessDefinitionId()
    {
        return $this->project->workflow_template_id;
    }

    private function getProcessInstanceId()
    {
        return $this->project->workflow_instance_ref;
    }

    private function getBusinessKey()
    {
        return 'workflow.' . $this->project->id;
    }
}
