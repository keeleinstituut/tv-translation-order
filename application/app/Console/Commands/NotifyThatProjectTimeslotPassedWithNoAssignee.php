<?php

namespace App\Console\Commands;

use App\Enums\ProjectStatus;
use App\Models\Assignment;
use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;

class NotifyThatProjectTimeslotPassedWithNoAssignee extends Command
{
    protected $signature = 'app:notify-that-project-timeslot-passed-with-no-assignee';

    protected $description = 'Notify managers about calendar projects whose timeslot has passed with no vendor assigned';

    public function handle(NotificationPublisher $notificationPublisher): void
    {
        Project::query()
            ->where('is_calendar_project', true)
            ->whereIn('status', [ProjectStatus::New, ProjectStatus::Registered])
            ->whereNotNull('event_end_at')
            ->where('event_end_at', '<', Carbon::now())
            ->whereDoesntHave('assignments', fn ($q) => $q->whereNotNull('assigned_vendor_id'))
            ->whereHas('assignments', fn ($q) => $q->whereNull('timeslot_passed_notification_sent_at'))
            ->with([
                'assignments' => fn ($q) => $q->whereNull('timeslot_passed_notification_sent_at'),
                'assignments.jobDefinition',
                'managerInstitutionUser',
                'institution',
            ])
            ->each(function (Project $project) use ($notificationPublisher) {
                $manager = $project->managerInstitutionUser;
                $receiverEmail = $manager?->email;
                $receiverName = $manager?->getUserFullName();

                if (empty($receiverEmail)) {
                    $receiverEmail = $project->institution?->email;
                    $receiverName = $project->institution?->name;
                }

                if (empty($receiverEmail)) {
                    return;
                }

                $project->assignments->each(function (Assignment $assignment) use (
                    $notificationPublisher,
                    $project,
                    $receiverEmail,
                    $receiverName,
                ) {
                    $notificationPublisher->publishEmailNotification(
                        EmailNotificationMessage::make([
                            'notification_type' => NotificationType::ProjectTimeslotPassedWithNoAssignee,
                            'receiver_email' => $receiverEmail,
                            'receiver_name' => $receiverName,
                            'variables' => [
                                'project' => $project->only(['ext_id']),
                                'assignment' => $assignment->only('ext_id'),
                                'job_definition' => $assignment->jobDefinition?->only('job_short_name'),
                            ],
                        ]),
                        $project->institution_id
                    );

                    $assignment->timeslot_passed_notification_sent_at = Carbon::now();
                    $assignment->saveOrFail();
                });
            });
    }
}
