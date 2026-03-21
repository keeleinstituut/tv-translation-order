<?php

namespace App\Http\Controllers\API;

use App\Helpers\IntervalsUtil;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\VendorCalendarRequest;
use App\Http\Resources\API\VendorCalendarDaySummaryResource;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Models\VendorEmergencySchedule;
use App\Policies\VendorCalendarEntryPolicy;
use App\Policies\VendorPolicy;
use App\Repositories\Calendar\CalendarVendorRepository;
use App\Repositories\Calendar\WorktimeRepository;
use App\Services\Calendar\VendorWorkingHoursResolver;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

class VendorCalendarController extends Controller
{
    public function __construct(
        private readonly VendorWorkingHoursResolver $workingHoursResolver,
        private readonly CalendarVendorRepository   $vendorRepo,
        private readonly WorktimeRepository         $worktimeRepo,
        AuditLogPublisher                           $auditLogPublisher,
    ) {
        parent::__construct($auditLogPublisher);
    }

    /**
     * @throws \Throwable
     */
    #[OA\Get(
        path: '/vendors/{vendor}/calendar',
        summary: 'Per-day calendar summary for a vendor',
        tags: ['Calendar'],
        parameters: [
            new OAH\UuidPath('vendor'),
            new OA\QueryParameter(name: 'date_from', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'date_to', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorCalendarDaySummaryResource::class, description: 'Per-day calendar summary')]
    public function index(VendorCalendarRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VendorCalendarEntry::class);

        $vendor = Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->findOrFail($request->route('vendor'));

        $dateFrom = Carbon::parse($request->validated('date_from'));
        $dateTo = Carbon::parse($request->validated('date_to'));

        $periodStart = $dateFrom->copy()->startOfDay()->utc();
        $periodEnd = $dateTo->copy()->endOfDay()->utc();

        $vendor->load('institutionUser');
        $institutionUserWorktime = $vendor->institutionUser
            ?->only(VendorWorkingHoursResolver::WORKTIME_COLUMNS);

        $institutionWorktime = $this->worktimeRepo->getInstitutionWorktime(
            $vendor->institutionUser?->institution_id ?? ''
        );

        $entries = VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
            ->where('vendor_id', $vendor->id)
            ->overlapping($periodStart, $periodEnd)
            ->get(['id', 'vendor_id', 'start_at', 'end_at', 'assignment_id']);

        $emergencySchedules = $this->vendorRepo->getEmergencySchedulesForVendors(
            collect([$vendor->id]), $dateFrom, $dateTo
        )->get($vendor->id, collect());

        $days = [];
        $cursor = $dateFrom->copy();

        while ($cursor->lte($dateTo)) {
            $dayStart = $cursor->copy()->startOfDay()->utc();
            $dayEnd = $cursor->copy()->endOfDay()->utc();
            $dateString = $cursor->toDateString();

            $isEmergency = $emergencySchedules->contains(
                fn(VendorEmergencySchedule $s) => $s->start_date->lte($cursor) && $s->end_date->gte($cursor)
            );

            if ($isEmergency) {
                $days[] = [
                    'date' => $dateString,
                    'booked_hours' => null,
                    'total_hours' => null,
                    'is_emergency' => true,
                    'is_fully_booked' => false,
                ];
                $cursor->addDay();
                continue;
            }

            $workWindow = $this->workingHoursResolver->getWorkingWindow(
                $institutionUserWorktime,
                $institutionWorktime,
                $cursor,
            );

            $totalHours = $workWindow !== null
                ? ($workWindow[1] - $workWindow[0]) / 3600
                : null;

            $dayEntries = $entries->filter(
                fn(VendorCalendarEntry $e) => $e->start_at->lt($dayEnd) && $e->end_at->gt($dayStart)
            );

            $clippedNonAssignment = $dayEntries
                ->filter(fn(VendorCalendarEntry $e) => $e->assignment_id === null)
                ->map(fn(VendorCalendarEntry $e) => [
                    'start_ts' => max($e->start_at->timestamp, $dayStart->timestamp),
                    'end_ts' => min($e->end_at->timestamp, $dayEnd->timestamp),
                ]);

            $freeIntervals = IntervalsUtil::subtractIntervals($workWindow, $clippedNonAssignment);
            $isFullyBooked = $workWindow !== null && empty($freeIntervals);

            if ($isFullyBooked) {
                $days[] = [
                    'date' => $dateString,
                    'booked_hours' => null,
                    'total_hours' => null,
                    'is_emergency' => false,
                    'is_fully_booked' => true,
                ];
                $cursor->addDay();
                continue;
            }

            $bookedHours = $dayEntries
                ->filter(fn(VendorCalendarEntry $e) => $e->assignment_id !== null)
                ->sum(function (VendorCalendarEntry $e) use ($dayStart, $dayEnd): float {
                    $start = max($e->start_at->timestamp, $dayStart->timestamp);
                    $end = min($e->end_at->timestamp, $dayEnd->timestamp);

                    return max(0.0, ($end - $start) / 3600);
                });

            $days[] = [
                'date' => $dateString,
                'booked_hours' => round($bookedHours, 2),
                'total_hours' => $totalHours !== null ? round($totalHours, 2) : null,
                'is_emergency' => false,
                'is_fully_booked' => false,
            ];

            $cursor->addDay();
        }

        return VendorCalendarDaySummaryResource::collection($days);
    }
}
