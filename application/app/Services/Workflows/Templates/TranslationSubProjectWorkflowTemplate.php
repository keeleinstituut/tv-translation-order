<?php

namespace App\Services\Workflows\Templates;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class TranslationSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'translation-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'translation.bpmn';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'translation-sub-project';
    }
}
