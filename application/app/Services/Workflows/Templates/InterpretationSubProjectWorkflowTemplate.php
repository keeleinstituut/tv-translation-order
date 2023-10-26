<?php

namespace App\Services\Workflows\Templates;

use App\Models\Project;
use App\Models\SubProject;

class InterpretationSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'interpretation-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'interpretation-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'interpretation.bpmn';
    }
}
