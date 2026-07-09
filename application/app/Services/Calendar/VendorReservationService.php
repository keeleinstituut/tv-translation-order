<?php

namespace App\Services\Calendar;

use App\Enums\CandidateStatus;
use App\Exceptions\CalendarSlotConflictException;
use App\Jobs\ExpirePrebookJob;
use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\VendorCalendarEntry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

readonly class VendorReservationService
{
    public const int PREBOOK_DURATION_MINUTES = 10;

    /**
     * Create a temporary prebook reservation + queue expiration job.
     *
     * @throws CalendarSlotConflictException
     */
    public function prebook(
        string $vendorId,
        Carbon $startAt,
        Carbon $endAt,
        string $institutionUserId,
    ): VendorCalendarEntry
    {
        $calendarEntry = $this->createCalendarEntryWithConflictHandling([
            'vendor_id' => $vendorId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'prebook_institution_user_id' => $institutionUserId,
            'prebook_at' => now()->utc(),
        ]);

        // Expire job identifies the prebooking to release by institution user id.
        ExpirePrebookJob::dispatch($institutionUserId)
            ->afterCommit()
            ->delay(now()->addMinutes(self::PREBOOK_DURATION_MINUTES));

        return $calendarEntry;
    }

    public function getPrebookExpiresAt(VendorCalendarEntry $prebook): Carbon
    {
        return $prebook->prebook_at->addMinutes(self::PREBOOK_DURATION_MINUTES);
    }

    /**
     * Expire a prebook (idempotent).
     */
    public function releasePrebook(string $institutionUserId): void
    {
        $prebook = VendorCalendarEntry::where('prebook_institution_user_id', $institutionUserId)
            ->whereNull('assignment_id')->first();

        if (blank($prebook)) {
            return;
        }

        $prebook->forceDelete();
    }

    /**
     * Reserve a vendor for an assignment: create candidate + VCE atomically.
     *
     * @throws CalendarSlotConflictException
     */
    public function reserve(
        Assignment $assignment,
        string     $vendorId,
        Carbon     $startAt,
        Carbon     $endAt,
    ): Candidate
    {
        return DB::transaction(function () use ($assignment, $vendorId, $startAt, $endAt) {
            $candidate = Candidate::create([
                'assignment_id' => $assignment->id,
                'vendor_id' => $vendorId,
                'status' => CandidateStatus::New,
            ]);

            $this->createCalendarEntryWithConflictHandling([
                'vendor_id' => $vendorId,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'assignment_id' => $assignment->id,
            ]);

            return $candidate;
        });
    }

    /**
     * Reserve a vendor by converting an existing prebook into an assignment entry.
     *
     * @throws CalendarSlotConflictException
     */
    public function reserveFromPrebook(
        Assignment          $assignment,
        VendorCalendarEntry $prebook,
        ?TimeSlot           $timeSlot = null,
    ): Candidate
    {
        return DB::transaction(function () use ($assignment, $prebook, $timeSlot) {
            $prebook = VendorCalendarEntry::lockForUpdate()->find($prebook->id);

            if (!$prebook || filled($prebook->assignment_id)) {
                throw new CalendarSlotConflictException();
            }

            $candidate = Candidate::create([
                'assignment_id' => $assignment->id,
                'vendor_id' => $prebook->vendor_id,
                'status' => CandidateStatus::New,
            ]);

            $updateData = [
                'assignment_id' => $assignment->id,
                'prebook_institution_user_id' => null,
                'prebook_at' => null,
            ];

            if ($timeSlot) {
                $updateData['start_at'] = $timeSlot->bufferedStartAt;
                $updateData['end_at'] = $timeSlot->bufferedEndAt;
            }

            try {
                $prebook->update($updateData);
            } catch (QueryException $e) {
                if (in_array($e->getCode(), ['23P01', '23505'])) {
                    throw new CalendarSlotConflictException();
                }
                throw $e;
            }

            return $candidate;
        });
    }

    /**
     * Switch the assignment's reservation to a different vendor (cascade decline).
     *
     * No-op when the assignment is already reserved for $newVendorId — does NOT
     * touch start/end times in that case. If only the time slot needs to change
     * for the same vendor, update the VCE directly at the call site.
     *
     * @throws CalendarSlotConflictException
     */
    public function rotateToVendor(
        Assignment $assignment,
        string     $newVendorId,
        Carbon     $startAt,
        Carbon     $endAt,
    ): VendorCalendarEntry
    {
        $entry = $assignment->calendarEntry;
        if ($entry?->vendor_id === $newVendorId) {
            return $entry;
        }

        return DB::transaction(function () use ($assignment, $entry, $newVendorId, $startAt, $endAt) {
            $entry?->delete();

            return $this->createCalendarEntryWithConflictHandling([
                'vendor_id' => $newVendorId,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'assignment_id' => $assignment->id,
            ]);
        });
    }

    /**
     * Release all reservations for an assignment (clear candidates + VCE).
     */
    public function releaseAll(Assignment $assignment): void
    {
        Candidate::where('assignment_id', $assignment->id)->each(function (Candidate $candidate) {
            $candidate->delete();
        });

        VendorCalendarEntry::where('assignment_id', $assignment->id)->delete();
    }

    /**
     * @param array $attributes
     * @return VendorCalendarEntry
     *
     * @throws CalendarSlotConflictException
     */
    public function createCalendarEntryWithConflictHandling(array $attributes): VendorCalendarEntry
    {
        try {
            return DB::transaction(fn () => VendorCalendarEntry::create($attributes));
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23P01', '23505'])) {
                throw new CalendarSlotConflictException();
            }
            throw $e;
        }
    }
}
