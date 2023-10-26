<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class SwornTranslationWithReviewSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'sworn-translation-review-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'sworn-translation-review-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'sworn-translation-review.bpmn';
    }
}
