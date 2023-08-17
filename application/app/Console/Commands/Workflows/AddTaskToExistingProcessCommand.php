<?php

namespace app\Console\Commands\Workflows;

use App\Services\WorkflowService;
use Illuminate\Console\Command;

class AddTaskToExistingProcessCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:add-task {processInstanceId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imitate task splitting between multiple vendors';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $processInstanceId = $this->argument('processInstanceId');
        $response = WorkflowService::getProcessInstanceVariable($processInstanceId, 'subProjects');
        $subProjectsValue = $response['value'];
        foreach ($subProjectsValue as &$subProject) {
            $subProject['translations'][] = [
                [
                    'assignee' => '',
                    'candidateUsers' => [],
                ]
            ];
        }

        WorkflowService::updateProcessInstanceVariable(
            $processInstanceId, 'subProjects', [
                'value' => $subProjectsValue,
            ]
        );
    }
}
