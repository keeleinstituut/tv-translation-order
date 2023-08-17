<?php

namespace App\Services;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;

class WorkflowProcessInstanceService
{
    private Project $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function getTasks()
    {
        return WorkflowService::getTask([
            'processInstanceId' => $this->getProcessInstanceId(),
        ]);
    }

    public function updateProcessVariable($variableName, $newValue)
    {
        return WorkflowService::updateProcessInstanceVariable($this->getProcessInstanceId(), $variableName, $newValue);
    }

    public function startProcessInstance()
    {
        $params = [
            'businessKey' => $this->getBusinessKey(),
            'variables' => [
                'subProjects' => [
                    "value" => collect($this->project->subProjects)->map(function ($subProject) {
                        return [
                            'workflow_definition_id' => 'Sample-subproject',
                            'project_id' => $subProject->project_id,
                            'sub_project_id' => $subProject->id,
                            'translations' => collect($subProject->assignments)
                                ->filter(fn(Assignment $assignment) => $assignment->feature === Feature::JOB_TRANSLATION->value)
                                ->map(function ($assignment) {
                                    return [
                                        'assignee' => $assignment->assigned_vendor_id ?? '',
                                        'candidateUsers' => collect($assignment->caidndidates)->pluck('vendor_id')->toArray(),
                                    ];
                                })->values()->toArray(),
                            'revisions' => collect($subProject->assignments)
                                ->filter(fn(Assignment $assignment) => $assignment->feature === Feature::JOB_REVISION->value)
                                ->map(function ($assignment) {
                                    return [
                                        'assignee' => $assignment->assigned_vendor_id ?? '',
                                        'candidateUsers' => collect($assignment->caidndidates)->pluck('vendor_id')->toArray(),
                                    ];
                                })->values()->toArray(),
                            'overviews' => collect($subProject->assignments)
                                ->filter(fn(Assignment $assignment) => $assignment->feature === Feature::JOB_OVERVIEW->value)
                                ->map(function ($assignment) {
                                    return [
                                        'assignee' => $assignment->assigned_vendor_id ?? '',
                                        'candidateUsers' => collect($assignment->caidndidates)->pluck('vendor_id')->toArray(),
                                    ];
                                })->values()->toArray(),
                        ];
                    })->toArray(),
                ]
            ]
        ];

        return WorkflowService::startProcessDefinitionInstance($this->getProcessDefinitionId(), $params);
    }


    private function getProcessDefinitionId()
    {
        return $this->project->workflow_template_id;
    }

    private function getProcessInstanceId()
    {
        return $this->project->workflow_instance_ref;
    }

    private function getBusinessKey()
    {
        return 'workflow.' . $this->project->id;
    }
}
