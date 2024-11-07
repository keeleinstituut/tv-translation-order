<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class EditedTranslationCATReviewSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getWorkflowProcessDefinitionId(): string
    {
        return 'edited-translation-review-sub-project';
    }

    public function getId(): string
    {
        return 'cat-edited-translation-review-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'edited-translation-review.bpmn';
    }
}
