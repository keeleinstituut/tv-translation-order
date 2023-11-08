<?php

namespace App\Services\Workflows\Templates;

use App\Models\Project;
use App\Models\SubProject;

class SimultaneousInterpretationSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'simultaneous-interpretation-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'simultaneous-interpretation-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'simultaneous-interpretation.bpmn';
    }
}
