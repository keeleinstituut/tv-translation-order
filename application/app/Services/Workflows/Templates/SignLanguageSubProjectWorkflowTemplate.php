<?php

namespace App\Services\Workflows\Templates;

use App\Models\Project;
use App\Models\SubProject;

class SignLanguageSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'sign-language-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'sign-language-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'sign-language.bpmn';
    }
}
