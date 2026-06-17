<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Enums\TaskType;
use App\Jobs\Workflows\TrackProjectStatus;
use App\Models\InstitutionSetting;
use App\Models\Project;
use App\Services\Workflows\WorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AutoAcceptPendingProjects extends Command
{
    protected $signature = 'app:auto-accept-pending-projects';

    protected $description = 'Auto-accept projects whose auto-acceptance warning was sent at least a day ago';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $settingsByInstitution = InstitutionSetting::query()
            ->select(['institution_id', 'verbal_auto_acceptance_threshold_days', 'non_verbal_auto_acceptance_threshold_days'])
            ->get()
            ->keyBy('institution_id');

        $query = Project::query()
            ->whereIn('status', [ProjectStatus::SubmittedToClient, ProjectStatus::Corrected])
            ->whereNotNull('auto_acceptance_notification_sent_at')
            ->where('auto_acceptance_notification_sent_at', '<=', Carbon::now()->subDay());

        foreach ($query->cursor() as $project) {
            /** @var InstitutionSetting|null $setting */
            $setting = $settingsByInstitution->get($project->institution_id);

            if (empty($setting) || $setting->autoAcceptanceThresholdDaysFor($project->type_classifier_value_id) === null) {
                continue;
            }

            try {
                $this->autoAccept($project);
            } catch (Throwable $e) {
                Log::error('Failed to auto-accept project', [
                    'project_id' => $project->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @throws Throwable
     */
    private function autoAccept(Project $project): void
    {
        $task = $this->getClientReviewTaskOrFail($project);

        WorkflowService::completeProjectReviewTask(data_get($task, 'task.id'), true);

        TrackProjectStatus::dispatchSync($project);
    }

    private function getClientReviewTaskOrFail(Project $project): array
    {
        $task = $project->workflow()->getTasksSearchResult()->getTasks()
            ->first(fn($task) => data_get($task, 'variables.task_type') === TaskType::ClientReview->value);

        if (empty($task) || empty(data_get($task, 'task.id'))) {
            throw new RuntimeException("Client review task not found for project: $project->id workflow ID: $project->workflow_instance_ref");
        }

        return $task;
    }
}
