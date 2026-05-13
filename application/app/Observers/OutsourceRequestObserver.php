<?php

namespace App\Observers;

use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use Illuminate\Support\Facades\DB;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;

readonly class OutsourceRequestObserver
{
    public function __construct(private NotificationPublisher $notificationPublisher)
    {
    }

    public function created(OutsourceRequest $request): void
    {
    }

    public function updated(OutsourceRequest $request): void
    {
        if (!$request->wasChanged('status')) {
            return;
        }

        if ($request->status === OutsourceRequestStatus::Cancelled) {
            $request->offers->each(function (OutsourceOffer $offer) use ($request) {
                $this->publishRequestCancelledEmailNotification($request, $offer);
            });
        }
    }

    private function publishRequestCancelledEmailNotification(OutsourceRequest $request, OutsourceOffer $offer): void
    {
        /**
         * For the OutsourceOfferStatus::AcceptedOffer email notifications are handled in ProjectObserver.
         * @see ProjectObserver::publishProjectCancelledEmailNotificationForAcceptedOffer
         */
        if ($offer->status !== OutsourceOfferStatus::RequestSent) {
            return;
        }

        $institution = $offer->institution;
        $assignment = $request->assignment;
        DB::afterCommit(function () use ($institution, $assignment, $request) {
            if (empty($institution->email)) {
                return;
            }

            $this->notificationPublisher->publishEmailNotification(
                EmailNotificationMessage::make([
                    'notification_type' => NotificationType::OutsourceRequestCancelled,
                    'receiver_email' => $institution->email,
                    'receiver_name' => $institution->name,
                    'variables' => [
                        'assignment' => $assignment->only(['ext_id']),
                        'request' => $request->only(['cancellation_reason']),
                    ],
                ]),
                $institution->id
            );
        });
    }
}
