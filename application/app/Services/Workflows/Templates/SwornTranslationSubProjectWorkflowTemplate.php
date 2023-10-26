<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class SwornTranslationSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'sworn-translation-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'sworn-translation-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'sworn-translation.bpmn';
    }
}
