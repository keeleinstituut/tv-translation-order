<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class EditSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'edit-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'edit-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'edit.bpmn';
    }
}
