<?php

namespace App\Services\Workflows\Templates;

use App\Models\Project;
use App\Models\SubProject;

class PostTranslationSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'post-translation-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'post-translation-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'post-translation.bpmn';
    }
}
