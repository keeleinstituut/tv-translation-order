<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class TranslationWithEditAndReviewSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'translation-edit-review-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'translation-edit-review-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'translation-edit-review.bpmn';
    }
}
