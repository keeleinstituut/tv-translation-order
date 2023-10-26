<?php

namespace App\Services\Workflows\Templates;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class ManuscriptTranslationSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'manuscript-translation-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'manuscript-translation.bpmn';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'manuscript-translation-sub-project';
    }
}
