<?php

namespace App\Helpers;

use App\Models\Assignment;
use App\Models\CachedEntities\InstitutionUser;
use Illuminate\Support\Facades\Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;

class SubProjectTaskMarkedAsDoneEmailNotificationMessageComposer
{
    public static function compose(Assignment $assignment, ?InstitutionUser $receiver, bool $isManager = false): ?EmailNotificationMessage
    {
        $receiverEmail = $receiver?->email;
        $receiverName = $receiver?->getUserFullName();

        if ($isManager && empty($receiverEmail)) {
            $institution = $assignment->subProject->project->institution;
            $receiverEmail = $receiver?->email ?: $institution?->email;
            $receiverName = $receiver?->getUserFullName() ?: $institution?->name;
        }

        if (empty($receiverEmail)) {
            return null;
        }

        /** @var JwtPayloadUser $jwtPayloadUser */
        $jwtPayloadUser = Auth::user();
        if ($jwtPayloadUser->institutionUserId === $assignment->assignee?->institution_user_id) {
            $vendor = $assignment->assignee;
        }

        return EmailNotificationMessage::make([
            'notification_type' => NotificationType::SubProjectTaskMarkedAsDone,
            'receiver_email' => $receiverEmail,
            'receiver_name' => $receiverName,
            'variables' => [
                'user' => ['name' => implode(' ', [$jwtPayloadUser->forename, $jwtPayloadUser->surname])],
                'vendor' => isset($vendor) ? $vendor->only(['company_name']) : [],
                'sub_project' => $assignment->subProject->only(['ext_id']),
                'job_definition' => $assignment->jobDefinition?->only(['job_short_name'])
            ]
        ]);
    }
}
