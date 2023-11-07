<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class TranslationCATWithEditSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'cat-translation-edit-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'translation-edit-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'translation-edit.bpmn';
    }
}
