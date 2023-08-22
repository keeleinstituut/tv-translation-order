<?php

namespace App\Services\Workflows\Templates;

use App\Models\Project;
use App\Models\SubProject;

class SignLanguageSubProjectWorkflowTemplate extends BaseSubProjectWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'sign-language-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'sign-language-sub-project';
    }

    public function getVariables(Project $project): array
    {
        return $project->subProjects->map(function (SubProject $subProject) use ($project) {
            return [
                'workflow_definition_id' => $this->getWorkflowProcessDefinitionId(),
                'translations' => $subProject->assignments
                    ->map(function ($assignment) use ($subProject, $project) {
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
        return 'sign-language.bpmn';
    }
}
