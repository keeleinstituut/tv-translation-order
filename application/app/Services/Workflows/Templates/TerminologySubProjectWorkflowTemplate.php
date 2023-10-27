<?php

namespace App\Services\Workflows\Templates;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class TerminologySubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'terminology-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'terminology-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'terminology.bpmn';
    }
}
