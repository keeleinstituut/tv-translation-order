<?php

namespace App\Helpers;

use App\Models\Assignment;
use App\Models\CachedEntities\InstitutionUser;
use Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;

class SubProjectTaskMarkedAsDoneEmailNotificationMessageComposer
{
    public static function compose(Assignment $assignment, InstitutionUser $receiver): ?EmailNotificationMessage
    {
        if (empty($receiver->email)) {
            return null;
        }

        /** @var JwtPayloadUser $jwtPayloadUser */
        $jwtPayloadUser = Auth::user();
        if ($jwtPayloadUser->institutionUserId === $assignment->assignee?->institution_user_id) {
            $vendor = $assignment->assignee;
        }

        return EmailNotificationMessage::make([
            'notification_type' => NotificationType::SubProjectTaskMarkedAsDone,
            'receiver_email' => $receiver->email,
            'receiver_name' => $receiver->getUserFullName(),
            'variables' => [
                'user' => ['name' => implode(' ', [$jwtPayloadUser->forename, $jwtPayloadUser->surname])],
                'vendor' => isset($vendor) ? $vendor->only(['company_name']) : [],
                'sub_project' => $assignment->subProject->only(['ext_id']),
                'job_definition' => $assignment->jobDefinition?->only(['job_short_name'])
            ]
        ]);
    }
}
