<?php

namespace App\Services;

use App\Enums\Feature;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

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
        return WorkflowService::startProcessDefinitionInstance($this->getProcessDefinitionId(), [
            'businessKey' => $this->getBusinessKey(),
            'variables' => [
                'subProjects' => [
                    'value' => $this->project->subProjects->map(fn (SubProject $subProject) => [
                        'workflow_definition_id' => $subProject->project->typeClassifierValue->projectTypeConfig->workflow_process_definition_id,
                        'translations' => $subProject->assignments
//                            ->filter(fn (Assignment $assignment) => $assignment->feature === Feature::JOB_TRANSLATION->value)
                            ->map(fn (Assignment $assignment) => [
                                // TODO: Figure out which data needs to be sent upon initialization
                                'assignee' => '',
                                'candidateUsers' => [],
                            ])
                            ->all(),
                        'revisions' => $subProject->assignments
                            ->filter(fn (Assignment $assignment) => $assignment->feature === Feature::JOB_REVISION->value)
                            ->map(fn (Assignment $assignment) => [
                                // TODO: Figure out which data needs to be sent upon initialization
                                'assignee' => '',
                                'candidateUsers' => [],
                            ])
                            ->all(),
                        'overviews' => $subProject->assignments
                            ->filter(fn (Assignment $assignment) => $assignment->feature === Feature::JOB_OVERVIEW->value)
                            ->map(fn (Assignment $assignment) => [
                                // TODO: Figure out which data needs to be sent upon initialization
                                'assignee' => '',
                                'candidateUsers' => [],
                            ])
                            ->all(),
                    ]),
                ],
                //                'subProjects' => [
                //                    "value" => [
                //                        [
                //                            'workflow_definition_id' => 'Sample-subproject',
                //                            'translations' => [
                //                                [
                ////                                    'assignee' => fake()->name(),
                //                                    'assignee' => '',
                //                                    'candidateUsers' => implode(',', [
                //                                        fake()->name(),
                //                                        fake()->name(),
                //                                        fake()->name(),
                //                                    ]),
                //                                    'url' => fake()->url(),
                //                                ],
                //                                [
                //                                    'assignee' => fake()->name(),
                //                                    'candidateUsers' => '',
                //                                    'url' => fake()->url(),
                //                                ]
                //                            ],
                //                            'revisions' => [
                //                                [
                //                                    'assignee' => fake()->name(),
                //                                    'url' => fake()->url(),
                //                                    'candidateUsers' => '',
                //                                ]
                //                            ],
                //                            'overviews' => [
                //                                [
                //                                    'assignee' => fake()->name(),
                //                                    'url' => fake()->url(),
                //                                    'candidateUsers' => '',
                //                                ]
                //                            ]
                //                        ]
                //                    ]
                //                ]
            ],
        ]);
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
        return 'workflow.'.$this->project->id;
    }
}
