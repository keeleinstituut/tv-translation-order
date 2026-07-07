<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\InstitutionSetting;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use Throwable;

class NotifyProjectsPendingAutoAcceptance extends Command
{
    protected $signature = 'app:notify-projects-pending-auto-acceptance';

    protected $description = 'Warn clients that projects stuck in client review will be auto-accepted soon';

    /**
     * Execute the console command.
     * @throws Throwable
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
            ->with(['clientInstitutionUser', 'institution'])
            ->whereIn('status', [ProjectStatus::SubmittedToClient, ProjectStatus::Corrected])
            ->whereIn('institution_id', $settingsByInstitution->keys())
            ->whereNull('auto_acceptance_notification_sent_at')
            ->whereNotNull('submitted_to_client_review_at')
            ->orderBy('submitted_to_client_review_at');

        $groups = [];

        foreach ($query->cursor() as $project) {
            /** @var InstitutionSetting|null $setting */
            $setting = $settingsByInstitution->get($project->institution_id);

            if (empty($setting)) {
                continue;
            }

            $thresholdDays = $setting->autoAcceptanceThresholdDaysFor($project->type_classifier_value_id);

            if ($thresholdDays === null) {
                continue;
            }

            // We want to send the notification 24 hours before the threshold date
            $thresholdDays = max(0, $thresholdDays - 1);
            $threshold = $thresholdDays > 0 ? Carbon::now()->subDays($thresholdDays) : Carbon::now();

            if ($project->submitted_to_client_review_at > $threshold) {
                continue;
            }

            if (filled($project->corrected_at) && $project->corrected_at > $threshold) {
                continue;
            }

            $receiver = $this->resolveReceiver($project);

            if (filled($receiver['email'])) {
                $groups[$receiver['key']] ??= [
                    'email' => $receiver['email'],
                    'name' => $receiver['name'],
                    'institution_id' => $project->institution_id,
                    'projects' => [],
                ];
                $groups[$receiver['key']]['projects'][] = $project->only(['ext_id']);
            }

            $project->auto_acceptance_notification_sent_at = Carbon::now();
            $project->save();
        }

        foreach ($groups as $group) {
            $this->publishBatch($group, $notificationPublisher);
        }
    }

    /**
     * @return array{email: ?string, name: ?string, key: string}
     */
    private function resolveReceiver(Project $project): array
    {
        if (filled($email = $project->clientInstitutionUser?->email)) {
            return [
                'email' => $email,
                'name' => $project->clientInstitutionUser?->getUserFullName(),
                'key' => "user:$project->client_institution_user_id",
            ];
        }

        return [
            'email' => $project->institution?->email,
            'name' => $project->institution?->name,
            'key' => "institution:$project->institution_id",
        ];
    }

    /**
     * @param array{email: string, name: ?string, institution_id: ?string, projects: array<array{ext_id: ?string}>} $group
     * @throws Throwable
     */
    private function publishBatch(array $group, NotificationPublisher $notificationPublisher): void
    {
        $notificationPublisher->publishEmailNotification(
            EmailNotificationMessage::make([
                'notification_type' => NotificationType::ProjectAutoAcceptancePending,
                'receiver_email' => $group['email'],
                'receiver_name' => $group['name'],
                'variables' => [
                    'projects' => $group['projects'],
                ],
            ]),
            $group['institution_id']
        );
    }
}
