<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class SwornTranslationCATWithReviewSubProjectWorkflowTemplate extends BaseSubProjectWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'cat-sworn-translation-review-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'sworn-translation-review-sub-project';
    }

    public function getVariables(Project $project): array
    {
        return $project->subProjects->map(function (SubProject $subProject) use ($project) {
            return [
                'workflow_definition_id' => $this->getWorkflowProcessDefinitionId(),
                'translations' => $subProject->assignments
                    ->filter(fn(Assignment $assignment) => $assignment->feature === Feature::JOB_TRANSLATION->value)
                    ->map(function (Assignment $assignment) use ($subProject, $project) {
                        return $this->buildUserTaskVariables(
                            $project,
                            $subProject,
                            $assignment,
                            true
                        );
                    })->values()->toArray(),
                'overviews' => $subProject->assignments
                    ->filter(fn(Assignment $assignment) => $assignment->feature === Feature::JOB_REVISION->value)
                    ->map(function (Assignment $assignment) use ($subProject, $project) {
                        return $this->buildUserTaskVariables(
                            $project,
                            $subProject,
                            $assignment,
                            true
                        );
                    })->values()->toArray(),
            ];
        })->toArray();
    }

    protected function getTemplateFileName(): string
    {
        return 'sworn-translation-review.bpmn';
    }
}
