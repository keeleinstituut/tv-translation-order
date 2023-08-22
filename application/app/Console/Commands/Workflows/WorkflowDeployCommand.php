<?php

namespace App\Console\Commands\Workflows;

use App\Services\Workflows\Templates\ProjectWorkflowTemplate;
use App\Services\Workflows\WorkflowService;
use App\Services\Workflows\SubProjectWorkflowTemplatePicker;
use Illuminate\Console\Command;
use RuntimeException;

class WorkflowDeployCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:deploy {id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy workflow(s) to Camunda';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $workflowProcessTemplateId = $this->argument('id');
        $deployed = collect();
        foreach (SubProjectWorkflowTemplatePicker::getTemplates() as $workflowTemplate) {
            if ($workflowProcessTemplateId && $workflowProcessTemplateId !== $workflowTemplate->getWorkflowProcessDefinitionId()) {
                continue;
            }

            if ($deployed->has($workflowTemplate->getWorkflowProcessDefinitionId())) {
                continue;
            }

            WorkflowService::createDeployment($workflowTemplate);
            $deployed->put($workflowTemplate->getWorkflowProcessDefinitionId(), true);
        }

        $projectWorkflowProcessTemplate = new ProjectWorkflowTemplate();
        if (empty($workflowProcessTemplateId) || $workflowProcessTemplateId === $projectWorkflowProcessTemplate->getWorkflowProcessDefinitionId()) {
            WorkflowService::createDeployment($projectWorkflowProcessTemplate);
            $deployed->put($projectWorkflowProcessTemplate->getWorkflowProcessDefinitionId(), true);
        }

        if ($deployed->isEmpty()) {
            throw new RuntimeException("Workflow template not found by ID $workflowProcessTemplateId");
        }
    }
}
