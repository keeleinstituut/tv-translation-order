<?php

namespace App\Services\Workflows\Templates;

class ProjectWorkflowTemplate extends BaseWorkflowTemplate implements WorkflowTemplateInterface
{
    public function getWorkflowProcessDefinitionId(): string
    {
        return 'project-workflow';
    }

    protected function getTemplateFileName(): string
    {
        return 'main.bpmn';
    }
}
