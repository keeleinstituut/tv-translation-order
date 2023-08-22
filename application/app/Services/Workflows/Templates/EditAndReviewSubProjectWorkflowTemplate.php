<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class EditAndReviewSubProjectWorkflowTemplate extends BaseSubProjectWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getWorkflowProcessDefinitionId(): string
    {
        return 'edit-review-sub-project';
    }

    public function getId(): string
    {
        return 'edit-review-sub-project';
    }

    public function getVariables(Project $project): array
    {
        return $project->subProjects->map(function (SubProject $subProject) use ($project) {
            return [
                'workflow_definition_id' => $this->getWorkflowProcessDefinitionId(),
                'revisions' => $subProject->assignments
                    ->filter(fn(Assignment $assignment) => $assignment->feature === Feature::JOB_REVISION->value)
                    ->map(function (Assignment $assignment) use ($subProject, $project) {
                        return $this->buildUserTaskVariables(
                            $project,
                            $subProject,
                            $assignment
                        );
                    })->values()->toArray(),
                'overviews' => $subProject->assignments
                    ->filter(fn(Assignment $assignment) => $assignment->feature === Feature::JOB_OVERVIEW->value)
                    ->map(function (Assignment $assignment) use ($subProject, $project) {
                        return $this->buildUserTaskVariables(
                            $project,
                            $subProject,
                            $assignment
                        );
                    })->values()->toArray(),
            ];
        })->toArray();
    }


    protected function getTemplateFileName(): string
    {
        return 'edit-review.bpmn';
    }
}
