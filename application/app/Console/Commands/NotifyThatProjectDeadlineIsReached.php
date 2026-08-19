<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;

class NotifyThatProjectDeadlineIsReached extends Command
{
    protected $signature = 'app:notify-users-that-project-deadline-reached';

    protected $description = 'Notify users that the project deadline is reached';

    /**
     * Execute the console command.
     */
    public function handle(NotificationPublisher $notificationPublisher): void
    {
        Project::whereNotIn('status', [ProjectStatus::Cancelled, ProjectStatus::Accepted])
            ->whereNull('deadline_notification_sent_at')
            ->each(function (Project $project) use ($notificationPublisher) {
                $atLeastOneNotificationSent = false;
                if (empty($project->deadline_notification_sent_at) && filled($project->deadline_at) && $project->deadline_at < Carbon::now()) {
                    if (filled($client = $project->clientInstitutionUser) && filled($client->email)) {
                        $notificationPublisher->publishEmailNotification(
                            EmailNotificationMessage::make([
                                'notification_type' => NotificationType::ProjectDeadlineReached,
                                'receiver_email' => $client->email,
                                'receiver_name' => $client->getUserFullName(),
                                'variables' => [
                                    'project' => $project->only(['ext_id']),
                                ]
                            ]),
                            $project->institution_id
                        );
                        $atLeastOneNotificationSent = true;
                    }

                    $manager = $project->managerInstitutionUser;
                    $receiverEmail = $manager?->email;
                    $receiverName = $manager?->getUserFullName();

                    if (empty($receiverEmail)) {
                        $receiverEmail = $project->institution?->email;
                        $receiverName = $project->institution?->name;
                    }

                    if (filled($receiverEmail)) {
                        $notificationPublisher->publishEmailNotification(
                            EmailNotificationMessage::make([
                                'notification_type' => NotificationType::ProjectDeadlineReached,
                                'receiver_email' => $receiverEmail,
                                'receiver_name' => $receiverName,
                                'variables' => [
                                    'project' => $project->only(['ext_id']),
                                ]
                            ]),
                            $project->institution_id
                        );
                        $atLeastOneNotificationSent = true;
                    }

                    if ($atLeastOneNotificationSent) {
                        $project->deadline_notification_sent_at = Carbon::now();
                        $project->saveOrFail();
                    }
                }
            });
    }
}
