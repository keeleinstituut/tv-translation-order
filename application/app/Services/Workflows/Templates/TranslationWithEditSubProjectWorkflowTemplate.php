<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class TranslationWithEditSubProjectWorkflowTemplate extends BaseSubProjectWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'translation-edit-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'translation-edit-sub-project';
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
                            $assignment
                        );
                    })->values()->toArray(),
                'revisions' => $subProject->assignments
                    ->filter(fn(Assignment $assignment) => $assignment->feature === Feature::JOB_REVISION->value)
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
        return 'translation-edit.bpmn';
    }
}
