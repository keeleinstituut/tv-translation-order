<?php

namespace App\Services\Workflows\Templates;

use App\Models\Project;

interface SubProjectWorkflowTemplateInterface extends WorkflowTemplateInterface
{
    /**
     * @return string process template ID
     */


    /**
     * @param Project $project
     * @return array variables that are needed to start process instance
     */
    public function getVariables(Project $project): array;

    /**
     * @return string returns ID of the workflow definition ID with prefix that CAT tool should be user
     */
    public function getId(): string;
}
