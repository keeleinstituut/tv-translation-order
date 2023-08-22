<?php

namespace app\Console\Commands\Workflows;

use App\Services\Workflows\WorkflowService;
use Illuminate\Console\Command;

class CompleteProjectReviewTaskCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:complete-project-review {taskId} {successful=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Complete review of the project';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $taskId = $this->argument('taskId');
        $response = WorkflowService::completeTask($taskId, [
            'variables' => [
                "acceptedByClient" => [
                    'value' => boolval($this->argument('successful')),
                ]
            ],
            'withVariablesInReturn' => true
        ]);

        var_dump($response);
    }
}
