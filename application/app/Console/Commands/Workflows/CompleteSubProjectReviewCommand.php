<?php

namespace app\Console\Commands\Workflows;

use App\Services\WorkflowService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

class CompleteSubProjectReviewCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:complete-review {taskId} {successful=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Complete review of the sub-project';

    /**
     * Execute the console command.
     * @throws RequestException
     */
    public function handle()
    {
        $taskId = $this->argument('taskId');
        $response = WorkflowService::completeTask($taskId, [
            'variables' => [
                "subProjectPartFinished.$taskId" => [
                    'value' => boolval($this->argument('successful')),
                ]
            ],
            'withVariablesInReturn' => false
        ]);

        var_dump($response);
    }
}
