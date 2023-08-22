<?php

namespace App\Services\Workflows\Templates;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class EditSubProjectWorkflowTemplate extends BaseSubProjectWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'edit-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'edit-sub-project';
    }

    public function getVariables(Project $project): array
    {
        return $project->subProjects->map(function (SubProject $subProject) use ($project) {
            return [
                'workflow_definition_id' => $this->getWorkflowProcessDefinitionId(),
                'revisions' => $subProject->assignments
                    ->map(function (Assignment $assignment) use ($subProject, $project) {
                        return $this->buildUserTaskVariables(
                            $project,
                            $subProject,
                            $assignment
                        );
                    })->toArray(),
            ];
        })->toArray();
    }

    protected function getTemplateFileName(): string
    {
        return 'edit.bpmn';
    }
}
