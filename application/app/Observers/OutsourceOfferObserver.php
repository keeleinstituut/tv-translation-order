<?php

namespace App\Observers;

use App\Enums\OutsourceOfferStatus;
use App\Jobs\Workflows\SyncWorkflowVariables;
use App\Models\OutsourceOffer;
use Illuminate\Support\Facades\DB;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;

readonly class OutsourceOfferObserver
{
    public function __construct(private NotificationPublisher $notificationPublisher)
    {
    }

    public function created(OutsourceOffer $offer): void
    {
        if ($offer->status === OutsourceOfferStatus::RequestSent) {
            $this->publishRequestSentEmailNotification($offer);
        }
    }

    public function updated(OutsourceOffer $offer): void
    {
        if (!$offer->wasChanged('status')) {
            return;
        }

        match ($offer->status) {
            OutsourceOfferStatus::RequestSent => $this->publishRequestSentEmailNotification($offer),
            OutsourceOfferStatus::RequestAccepted => $this->publishRequestAcceptedEmailNotification($offer),
            OutsourceOfferStatus::RequestDeclined => $this->publishRequestDeclinedEmailNotification($offer),
            OutsourceOfferStatus::RequestExpired => $this->publishRequestExpiredEmailNotification($offer),
            OutsourceOfferStatus::OfferAccepted => $this->publishOfferAcceptedEmailNotification($offer),
            OutsourceOfferStatus::OfferDeclined => $this->publishOfferDeclinedEmailNotification($offer),
            default => null,
        };

        $hadOfferAcceptedStatus = data_get($offer->getChanges(), 'status') === OutsourceOfferStatus::OfferAccepted;

        if ($offer->status === OutsourceOfferStatus::OfferAccepted || $hadOfferAcceptedStatus) {
            // We need to update institution_id for the task that belongs to the shared assignment
            SyncWorkflowVariables::dispatchSync($offer->outsourceRequest->assignment);
        }
    }

    private function publishRequestSentEmailNotification(OutsourceOffer $offer): void
    {
        $institution = $offer->institution;
        if (empty($institution->email)) {
            return;
        }

        $institutionId = $offer->outsourceRequest->institutionUser->institution_id;
        $assignment = $offer->outsourceRequest->assignment;

        DB::afterCommit(function () use ($institution, $institutionId, $assignment) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::OutsourceOfferRequestSent,
                    'receiver_email' => $institution->email,
                    'receiver_name' => $institution->name,
                    'variables' => ['assignment' => $assignment->only(['ext_id'])],
                ]),
                $institutionId
            );
        });
    }

    private function publishRequestAcceptedEmailNotification(OutsourceOffer $offer): void
    {
        [$receiverEmail, $receiverName] = $this->resolveRequestorRecipient($offer) ?? [null, null];
        if (empty($receiverEmail)) {
            return;
        }

        $institutionId = $offer->outsourceRequest->institutionUser->institution_id;
        $assignment = $offer->outsourceRequest->assignment;

        DB::afterCommit(function () use ($receiverEmail, $receiverName, $institutionId, $assignment) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::OutsourceOfferRequestAccepted,
                    'receiver_email' => $receiverEmail,
                    'receiver_name' => $receiverName,
                    'variables' => ['assignment' => $assignment->only(['ext_id'])],
                ]),
                $institutionId
            );
        });
    }

    private function publishRequestDeclinedEmailNotification(OutsourceOffer $offer): void
    {
        [$receiverEmail, $receiverName] = $this->resolveRequestorRecipient($offer) ?? [null, null];
        if (empty($receiverEmail)) {
            return;
        }

        $institutionId = $offer->outsourceRequest->institutionUser->institution_id;
        $assignment = $offer->outsourceRequest->assignment;
        $offerInstitution = $offer->institution;

        DB::afterCommit(function () use ($receiverEmail, $receiverName, $institutionId, $assignment, $offerInstitution) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::OutsourceOfferRequestDeclined,
                    'receiver_email' => $receiverEmail,
                    'receiver_name' => $receiverName,
                    'variables' => [
                        'assignment' => $assignment->only(['ext_id']),
                        'offer_institution' => $offerInstitution->only(['name']),
                    ],
                ]),
                $institutionId
            );
        });
    }

    private function publishRequestExpiredEmailNotification(OutsourceOffer $offer): void
    {
        $request = $offer->outsourceRequest;
        $institutionId = $request->institutionUser->institution_id;
        $assignment = $request->assignment;

        [$receiverEmail, $receiverName] = $this->resolveRequestorRecipient($offer) ?? [null, null];

        DB::afterCommit(function () use ($receiverEmail, $receiverName, $institutionId, $assignment) {
            if (empty($receiverEmail)) {
                return;
            }
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::OutsourceOfferRequestExpired,
                    'receiver_email' => $receiverEmail,
                    'receiver_name' => $receiverName,
                    'variables' => ['assignment' => $assignment->only(['ext_id'])],
                ]),
                $institutionId
            );
        });
    }

    private function publishOfferAcceptedEmailNotification(OutsourceOffer $offer): void
    {
        $institution = $offer->institution;
        if (empty($institution?->email)) {
            return;
        }

        $assignment = $offer->outsourceRequest->assignment;
        DB::afterCommit(function () use ($institution, $assignment) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::OutsourceOfferAccepted,
                    'receiver_email' => $institution->email,
                    'receiver_name' => $institution->name,
                    'variables' => ['assignment' => $assignment->only(['ext_id'])],
                ]),
                $institution->id
            );
        });
    }

    private function publishOfferDeclinedEmailNotification(OutsourceOffer $offer): void
    {
        $priorStatus = $offer->getOriginal('status');
        if (!in_array($priorStatus, [OutsourceOfferStatus::RequestSent, OutsourceOfferStatus::RequestAccepted])) {
            return;
        }

        $institution = $offer->institution;
        if (empty($institution->email)) {
            return;
        }

        $institutionId = $offer->outsourceRequest->institutionUser->institution_id;
        $assignment = $offer->outsourceRequest->assignment;

        DB::afterCommit(function () use ($institution, $institutionId, $assignment, $offer) {
            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::OutsourceOfferDeclined,
                    'receiver_email' => $institution->email,
                    'receiver_name' => $institution->name,
                    'variables' => [
                        'assignment' => $assignment->only(['ext_id']),
                        'offer' => $offer->only(['rejection_comment']),
                    ],
                ]),
                $institutionId
            );
        });
    }

    /**
     * @return array{string, string}|null [email, name] of the requestor TPM,
     *   falling back to the request's owning institution if the user has no email.
     */
    private function resolveRequestorRecipient(OutsourceOffer $offer): ?array
    {
        $request = $offer->outsourceRequest;
        $requestor = $request->institutionUser;
        $email = $requestor?->email;
        $name = $requestor?->getUserFullName();

        if (empty($email)) {
            $institution = $requestor->institution;
            $email = $institution?->email;
            $name = $institution?->name;
        }

        return empty($email) ? null : [$email, $name];
    }
}
