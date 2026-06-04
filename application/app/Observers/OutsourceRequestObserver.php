<?php

namespace App\Observers;

use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Jobs\Workflows\SyncWorkflowVariables;
use App\Models\Candidate;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use App\Models\Volume;
use Illuminate\Support\Facades\DB;
use NotificationClient\DataTransferObjects\EmailNotificationMessage;
use NotificationClient\Enums\NotificationType;
use NotificationClient\Services\NotificationPublisher;
use Throwable;

readonly class OutsourceRequestObserver
{
    public function __construct(private NotificationPublisher $notificationPublisher)
    {
    }

    public function created(OutsourceRequest $request): void
    {
    }

    /**
     * @throws Throwable
     */
    public function updated(OutsourceRequest $request): void
    {
        if (!$request->wasChanged('status')) {
            return;
        }

        if ($request->status === OutsourceRequestStatus::Cancelled) {
            $request->assignment->candidates->each(function (Candidate $candidate) use ($request) {
                if ($candidate->vendor->institutionUser->institution_id !== $request->ownerInstitution->id) {
                    $candidate->delete();
                }
            });

            // We need to update institution_id for the task that belongs to the shared assignment
            SyncWorkflowVariables::dispatchSync($request->assignment);

            $request->offers->each(function (OutsourceOffer $offer) use ($request) {
                $this->publishRequestCancelledEmailNotification($request, $offer);
            });

        }

        if ($request->status === OutsourceRequestStatus::Fulfilled && filled($request->price)) {
            $this->updateCachedPrices($request);
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

    /**
     * @throws Throwable
     */
    private function updateCachedPrices(OutsourceRequest $outsourceRequest): void
    {
        if (filled($assignment = $outsourceRequest->assignment)) {
            $assignment->price = $assignment->getPriceCalculator()->getPrice();
            $assignment->saveOrFail();

            if (filled($subProject = $assignment->subProject)) {
                $subProject->price = $subProject->getPriceCalculator()->getPrice();
                $subProject->saveOrFail();
            }

            if (filled($project = $subProject?->project)) {
                $project->price = $project->getPriceCalculator()->getPrice();
                $project->saveOrFail();
            }
        }
    }
}
