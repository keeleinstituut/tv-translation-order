<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\InstitutionSetting;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;

class NotifyProjectsPendingAutoAcceptance extends Command
{
    protected $signature = 'app:notify-projects-pending-auto-acceptance';

    protected $description = 'Warn clients that projects stuck in client review will be auto-accepted soon';

    /**
     * Execute the console command.
     */
    public function handle(NotificationPublisher $notificationPublisher): void
    {
        $settingsByInstitution = InstitutionSetting::query()
            ->select(['institution_id', 'verbal_auto_acceptance_threshold_days', 'non_verbal_auto_acceptance_threshold_days'])
            ->where(fn ($q) => $q
                ->whereNotNull('verbal_auto_acceptance_threshold_days')
                ->orWhereNotNull('non_verbal_auto_acceptance_threshold_days')
            )
            ->get()
            ->keyBy('institution_id');

        if ($settingsByInstitution->isEmpty()) {
            return;
        }

        $query = Project::query()
            ->where('status', ProjectStatus::SubmittedToClient)
            ->whereIn('institution_id', $settingsByInstitution->keys())
            ->whereNull('auto_acceptance_notification_sent_at');

        foreach ($query->cursor() as $project) {
            /** @var InstitutionSetting|null $setting */
            $setting = $settingsByInstitution->get($project->institution_id);

            if (empty($setting)) {
                return;
            }

            $thresholdDays = ClassifierValue::isVerbalProjectType($project->type_classifier_value_id)
                ? $setting->verbal_auto_acceptance_threshold_days
                : $setting->non_verbal_auto_acceptance_threshold_days;

            if ($thresholdDays === null) {
                return;
            }

            if ($project->submitted_to_client_review_at > Carbon::now()->subDays($thresholdDays)) {
                return;
            }


            $this->publishProjectPendingAutoAcceptanceEmailNotification($project, $notificationPublisher);
            $project->auto_acceptance_notification_sent_at = Carbon::now();
            $project->save();
        }
    }

    private function publishProjectPendingAutoAcceptanceEmailNotification(Project $project, NotificationPublisher $notificationPublisher): void
    {
        $receiverEmail = $project->clientInstitutionUser?->email;
        $receiverName = $project->clientInstitutionUser?->getUserFullName();

        if (empty($receiverEmail)) {
            $receiverEmail = $project->institution?->email;
            $receiverName = $project->institution?->name;
        }

        if (filled($receiverEmail)) {
            DB::afterCommit(function () use ($project, $receiverEmail, $receiverName, $notificationPublisher) {
                $notificationPublisher->publishEmailNotification(
                    EmailNotificationMessage::make([
                        'notification_type' => NotificationType::ProjectAutoAcceptancePending,
                        'receiver_email' => $receiverEmail,
                        'receiver_name' => $receiverName,
                        'variables' => [
                            'project' => $project->only(['ext_id']),
                        ]
                    ]),
                    $project->institution_id
                );
            });
        }
    }
}
