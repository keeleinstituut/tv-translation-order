<?php

namespace App\Services\Workflows\Templates;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;

abstract class BaseSubProjectWorkflowTemplate extends BaseWorkflowTemplate
{
    protected function buildUserTaskVariables(Project $project, SubProject $subProject, Assignment $assignment = null, bool $withCATTool = false): array
    {
        return [
            'assignment_id' => $assignment->id,
            'sub_project_id' => $subProject->id,
            'with_cat_tool' => $withCATTool,
            'machine_translation' => false,
            'price' => '',
            'volume' => [],
            'event_start_at' => '',
            'deadline' => $project->deadline_at,
            'accepted_at' => null,
            'completed_at' => null,
            'assignee' => $assignment->assigned_vendor_id ?? '',
            'candidateUsers' => collect($assignment->candidates)->pluck('vendor_id')->toArray(),
        ];
    }
}
