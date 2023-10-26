<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class TerminologyWithReviewSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'terminology-review-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'terminology-review-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'terminology-review.bpmn';
    }
}
