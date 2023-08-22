<?php

namespace app\Console\Commands\Workflows;

use App\Services\Workflows\WorkflowService;
use Illuminate\Console\Command;

class CancelProjectCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workflow:cancel-project {processInstanceId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cancel project';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $processInstanceId = $this->argument('processInstanceId');
        $response = WorkflowService::deleteProcessInstances([$processInstanceId], 'Project cancelled');
        var_dump($response);
    }
}
