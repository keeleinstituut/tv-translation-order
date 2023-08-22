<?php

namespace App\Services\Workflows\Templates;

use App\Models\Project;
use App\Models\SubProject;

class SimultaneousInterpretationSubProjectWorkflowTemplate extends BaseSubProjectWorkflowTemplate implements SubProjectWorkflowTemplateInterface
{
    public function getId(): string
    {
        return 'interpretation-sub-project';
    }

    public function getWorkflowProcessDefinitionId(): string
    {
        return 'interpretation-sub-project';
    }

    public function getVariables(Project $project): array
    {
        return $project->subProjects->map(function (SubProject $subProject) use ($project) {
            return [
                'workflow_definition_id' => $this->getWorkflowProcessDefinitionId(),
                'interpretations' => $subProject->assignments
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
        return 'simultaneous-interpretation.bpmn';
    }
}
