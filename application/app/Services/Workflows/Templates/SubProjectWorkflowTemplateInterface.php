<?php

namespace App\Services\Workflows\Templates;

interface SubProjectWorkflowTemplateInterface extends WorkflowTemplateInterface
{
    /**
     * @return string returns internal ID of the workflow template
     */
    public function getId(): string;

    /**
     * @return string returns workflow process definition ID
     */
    public function getWorkflowProcessDefinitionId(): string;
}
