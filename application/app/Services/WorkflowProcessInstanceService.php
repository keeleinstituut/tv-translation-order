<?php

namespace App\Services;

use App\Models\Project;

class WorkflowProcessInstanceService
{
    private Project $project;

    public function __construct(Project $project) {
        $this->project = $project;
    }

    public function getTasks() {
        return WorkflowService::getTask([
            "processInstanceId" => $this->getProcessInstanceId(),
        ]);
    }

    public function updateProcessVariable($variableName, $newValue) {
        return WorkflowService::updateProcessInstanceVariable($this->getProcessInstanceId(), $variableName, $newValue);
    }

    public function startProcessInstance() {
        return WorkflowService::startProcessDefinitionInstance($this->getProcessDefinitionId(), [
            'businessKey' => $this->getBusinessKey(),
            'variables' => [
                'subProjects' => [
                    "value" => collect($this->project->subProjects)->map(function ($subProject) {
                       return [
                           'workflow_definition_id' => 'Sample-subproject',
                           'translations' => collect($subProject->assignments)->map(function ($assignment) {
                               return [
                                   'assignee' => $assignment->assigned_vendor_ ?? '',
                                   'candidateUsers' => collect($assignment->caidndidates)->pluck('vendor_id'),
                               ];
                           }),
                           'revisions' => [

                           ],
                           'overviews' => [

                           ]
                       ];
                    }),
                ]
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
            ]
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
        return 'workflow.' . $this->project->id;
    }
}
