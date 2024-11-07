<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class SwornTranslationCATSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'cat-sworn-translation-sub-project';
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
