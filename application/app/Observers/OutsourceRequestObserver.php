<?php

namespace App\Observers;

use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Jobs\Workflows\UpdateSharedAssignmentInstitutionInsideWorkflow;
use App\Models\Candidate;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use Throwable;

readonly class OutsourceRequestObserver
{
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

            UpdateSharedAssignmentInstitutionInsideWorkflow::dispatchSync($request->assignment);
            // Bulk update is not used here to fire observer events
            $request->offers()
                ->whereNotIn('status', [
                    OutsourceOfferStatus::RequestPending,
                    OutsourceOfferStatus::OfferDeclined
                ])
                ->each(fn (OutsourceOffer $offer) => $offer->update(['status' => OutsourceOfferStatus::RequestCancelled]));

        }

        if ($request->status === OutsourceRequestStatus::Fulfilled && filled($request->price)) {
            $this->updateCachedPrices($request);
        }
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
