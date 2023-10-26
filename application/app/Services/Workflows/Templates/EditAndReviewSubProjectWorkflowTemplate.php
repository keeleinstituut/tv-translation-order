<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class EditAndReviewSubProjectWorkflowTemplate extends BaseWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getWorkflowProcessDefinitionId(): string
    {
        return 'edit-review-sub-project';
    }

    public function getId(): string
    {
        return 'edit-review-sub-project';
    }

    protected function getTemplateFileName(): string
    {
        return 'edit-review.bpmn';
    }
}
