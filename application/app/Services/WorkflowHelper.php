<?php

namespace App\Services;

use RuntimeException;

class WorkflowHelper
{
    public static function markSubProjectAsFinished(string $processInstanceId, string $subProjectId): void
    {
        $response = WorkflowService::getProcessInstanceVariable($processInstanceId, 'subProjects');
        $subProjectsValue = $response['value'];
        $markedAsFinished = false;
        foreach ($subProjectsValue as &$subProject) {
            if ($subProject['sub_project_id'] === $subProjectId) {
                $subProject['finished'] = true;
                $markedAsFinished = true;
                break;
            }
        }

        if (!$markedAsFinished) {
            throw new RuntimeException("the sub-project not found inside process instance variables");
        }

        WorkflowService::updateProcessInstanceVariable(
            $processInstanceId, 'subProjects', [
                'value' => $subProjectsValue,
            ]
        );
    }
}
