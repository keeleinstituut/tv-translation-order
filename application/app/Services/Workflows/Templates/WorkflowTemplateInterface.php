<?php

namespace App\Services\Workflows\Templates;

interface WorkflowTemplateInterface
{
    /**
     * @return string BPMN content
     */
    public function getDefinition(): string;

    /**
     * @return string ID of the process from .bpmn
     */
    public function getWorkflowProcessDefinitionId(): string;
}
