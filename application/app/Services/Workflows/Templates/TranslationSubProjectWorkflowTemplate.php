<?php

namespace App\Services\Workflows\Templates;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

class TranslationSubProjectWorkflowTemplate extends BaseSubProjectWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'translation-sub-project';
    }

    public function getVariables(Project $project): array
    {
        return $project->subProjects->map(function (SubProject $subProject) use ($project) {
            return [
                'workflow_definition_id' => $this->getWorkflowProcessDefinitionId(),
                'translations' => $subProject->assignments
                    ->map(function (Assignment $assignment) use ($subProject, $project) {
                        return $this->buildUserTaskVariables(
                            $project,
                            $subProject,
                            $assignment
                        );
                    }),
            ];
        })->toArray();
    }

    protected function getTemplateFileName(): string
    {
        return 'translation.bpmn';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'translation-sub-project';
    }
}
