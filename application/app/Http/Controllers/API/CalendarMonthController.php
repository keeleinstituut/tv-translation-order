<?php

namespace App\Http\Controllers\API;

use App\Enums\CalendarRole;
use App\Helpers\IntervalsUtil;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\MonthAggregationRequest;
use App\Http\Resources\API\CalendarMonthProjectManagerResource;
use App\Http\Resources\API\CalendarMonthSlotsResource;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Policies\VendorCalendarEntryPolicy;
use App\Policies\VendorPolicy;
use App\Services\Calendar\CalendarData;
use App\Services\Calendar\CalendarDataLoader;
use App\Services\Calendar\CalendarRoleResolver;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use App\Http\OpenApiHelpers as OAH;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CalendarMonthController extends Controller
{
    public function __construct(
        private readonly CalendarDataLoader       $dataLoader,
        private readonly CalendarRoleResolver     $roleResolver,
        AuditLogPublisher                         $auditLogPublisher,
    )
    {
        parent::__construct($auditLogPublisher);
    }

    #[OA\Get(
        path: '/calendar/month',
        description: 'Vendor/Client: booked hours per language per day (vendor_hours as a float). TPM: booked hours keyed by vendor ID per language per day, plus full vendor metadata list.',
        summary: 'Month view — daily aggregation; response shape varies by acting user role',
        tags: ['Calendar'],
        parameters: [
            new OA\QueryParameter(name: 'date_from', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'date_to', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Response depends on caller role. See oneOf schemas.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    oneOf: [
                        new OA\Schema(ref: CalendarMonthSlotsResource::class, description: 'Vendor/Client: slots with language_id, date, and vendor_hours (float)'),
                        new OA\Schema(ref: CalendarMonthProjectManagerResource::class, description: 'TPM: available_slots with vendor_hours map (vendor_id → float) plus vendor metadata list'),
                    ]
                ),
            ]
        )
    )]
    public function index(MonthAggregationRequest $request): JsonResource
    {
        $this->authorize('viewAny', VendorCalendarEntry::class);

        $startAt = Carbon::parse($request->validated('date_from'))->startOfDay()->utc();
        $endAt = Carbon::parse($request->validated('date_to'))->endOfDay()->utc();

        return match ($this->roleResolver->resolve()) {
            CalendarRole::ProjectManager => $this->projectManagerView(
                $this->roleResolver->getInstitutionId(),
                $startAt,
                $endAt
            ),
            CalendarRole::Vendor => $this->vendorView(
                $this->roleResolver->getVendor(),
                $startAt,
                $endAt
            ),
            CalendarRole::Client => $this->clientView(
                $this->roleResolver->getInstitutionUserId(),
                $startAt,
                $endAt
            ),
            CalendarRole::Unknown => throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid role')
        };
    }

    private function vendorView(Vendor $vendor, Carbon $startAt, Carbon $endAt): CalendarMonthSlotsResource
    {
        $timestampedRows = $this->entriesToTimestampedRows(
            $this->assignmentEntriesBaseQuery($startAt, $endAt)
                ->where('vendor_calendar_entries.vendor_id', $vendor->id)
                ->cursor()
        );

        return CalendarMonthSlotsResource::make([
            'slots' => $this->aggregateEntriesByLanguagePerSlot($timestampedRows, $startAt, $endAt),
        ]);
    }

    private function clientView(string $institutionUserId, Carbon $startAt, Carbon $endAt): CalendarMonthSlotsResource
    {
        $timestampedRows = $this->entriesToTimestampedRows(
            $this->assignmentEntriesBaseQuery($startAt, $endAt)
                ->forClient($institutionUserId)
                ->cursor()
        );

        return CalendarMonthSlotsResource::make([
            'slots' => $this->aggregateEntriesByLanguagePerSlot($timestampedRows, $startAt, $endAt),
        ]);
    }

    private function projectManagerView(string $institutionId, Carbon $startAt, Carbon $endAt): CalendarMonthProjectManagerResource
    {
        $data = $this->dataLoader->loadCoverageOnly($institutionId, $startAt, $endAt);

        if ($data->importedCalendarVendorIds->isEmpty()) {
            return CalendarMonthProjectManagerResource::make([
                'available_slots' => [],
                'vendors' => [],
            ]);
        }

        $timestampedRows = $this->entriesToTimestampedRows(
            $this->assignmentEntriesBaseQuery($startAt, $endAt)
                ->whereIn('vendor_calendar_entries.vendor_id', $data->importedCalendarVendorIds)
                ->cursor()
        );

        $slots = IntervalsUtil::generateSlots($startAt, $endAt, 24);
        $cursor = 0;
        $results = collect();

        foreach ($slots as $slotStart) {
            $slotEnd = $slotStart->copy()->addHours(24);
            $slotStartTs = $slotStart->timestamp;
            $slotEndTs = $slotEnd->timestamp;

            while ($cursor < $timestampedRows->count() && $timestampedRows[$cursor]['start_ts'] < $slotStartTs) {
                $cursor++;
            }

            $slotEntries = collect();
            for ($i = $cursor; $i < $timestampedRows->count(); $i++) {
                if ($timestampedRows[$i]['start_ts'] >= $slotEndTs) {
                    break;
                }
                $slotEntries->add($timestampedRows[$i]);
            }

            if ($slotEntries->isEmpty()) {
                continue;
            }

            $groupedByLanguage = $slotEntries->groupBy(fn(array $e) => $e['language_id']);

            foreach ($groupedByLanguage as $languageId => $languageEntries) {
                $vendorHours = [];

                foreach ($languageEntries as $row) {
                    $hours = ($row['end_ts'] - $row['start_ts']) / 3600;

                    if ($hours > 0) {
                        $vendorHours[$row['vendor_id']] = round(($vendorHours[$row['vendor_id']] ?? 0) + $hours, 2);
                    }
                }

                if (!empty($vendorHours)) {
                    $results->push([
                        'language_id' => $languageId,
                        'date' => $slotStart->toDateString(),
                        'vendor_hours' => $vendorHours,
                    ]);
                }
            }
        }

        return CalendarMonthProjectManagerResource::make([
            'available_slots' => $results->sortBy(['language_id', 'date'])->values()->all(),
            'vendors' => $this->buildVendorsMap($data),
        ]);
    }

    /**
     * Sweep sorted entries over 24-hour slots and return aggregated hours per language per day.
     *
     * @param Collection<int, array{start_ts: int, end_ts: int, vendor_id: string, language_id: string}> $entries
     * @return Collection<int, array{language_id: string, date: string, vendor_hours: float}>
     */
    private function aggregateEntriesByLanguagePerSlot(Collection $entries, Carbon $startAt, Carbon $endAt): Collection
    {
        if ($entries->isEmpty()) {
            return collect();
        }

        $slots = IntervalsUtil::generateSlots($startAt, $endAt, 24);
        $cursor = 0;
        $results = collect();

        foreach ($slots as $slotStart) {
            $slotEnd = $slotStart->copy()->addHours(24);
            $slotStartTs = $slotStart->timestamp;
            $slotEndTs = $slotEnd->timestamp;

            while ($cursor < $entries->count() && $entries->get($cursor)['start_ts'] < $slotStartTs) {
                $cursor++;
            }

            $slotEntries = collect();
            for ($i = $cursor; $i < $entries->count(); $i++) {
                if ($entries->get($i)['start_ts'] >= $slotEndTs) {
                    break;
                }
                $slotEntries->add($entries[$i]);
            }

            if ($slotEntries->isEmpty()) {
                continue;
            }

            $groupedByLanguage = $slotEntries->groupBy(fn(array $e) => $e['language_id']);

            foreach ($groupedByLanguage as $languageId => $languageEntries) {
                $vendorHours = $languageEntries->sum(function (array $e) {
                    return ($e['end_ts'] - $e['start_ts']) / 3600;
                });

                if ($vendorHours <= 0) {
                    continue;
                }

                $results->push([
                    'language_id' => $languageId,
                    'date' => $slotStart->toDateString(),
                    'vendor_hours' => round($vendorHours, 2),
                ]);
            }
        }

        return $results->sortBy(['language_id', 'date'])->values();
    }

    /**
     * Pre-compute timestamps to avoid repeated Carbon/attribute access in hot loops.
     *
     * @param LazyCollection<int, VendorCalendarEntry> $entries
     * @return Collection<int, array{start_ts: int, end_ts: int, vendor_id: string, language_id: string}>
     */
    private function entriesToTimestampedRows(LazyCollection $entries): Collection
    {
        return $entries->map(fn(VendorCalendarEntry $e) => [
            'start_ts' => $e->start_at->timestamp,
            'end_ts' => $e->end_at->timestamp,
            'vendor_id' => $e->vendor_id,
            'language_id' => $e->destination_language_classifier_value_id,
        ])->collect();
    }

    private function assignmentEntriesBaseQuery(Carbon $start, Carbon $end): EloquentBuilder
    {
        return VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
            ->whereNotNull('vendor_calendar_entries.assignment_id')
            ->where('vendor_calendar_entries.start_at', '<', $end)
            ->where('vendor_calendar_entries.end_at', '>', $start)
            ->join('assignments', 'assignments.id', '=', 'vendor_calendar_entries.assignment_id')
            ->join('sub_projects', 'sub_projects.id', '=', 'assignments.sub_project_id')
            ->select([
                'vendor_calendar_entries.start_at',
                'vendor_calendar_entries.end_at',
                'vendor_calendar_entries.vendor_id',
                'sub_projects.destination_language_classifier_value_id',
            ])
            ->orderBy('vendor_calendar_entries.start_at');
    }

    /**
     * @return array<int, array>
     */
    private function buildVendorsMap(CalendarData $data): array
    {
        $vendors = $data->internalVendorIds->isNotEmpty() ?
            Vendor::withGlobalScope('policy', VendorPolicy::scope())
                ->whereIn('id', $data->internalVendorIds)
                ->with('institutionUser')
                ->get() : collect();

        return $vendors->map(fn(Vendor $vendor) => [
            'id' => $vendor->id,
            'institutionUser' => $vendor->institutionUser,
            'languages' => $data->getLanguagesForVendor($vendor->id)->all(),
            'emergency_schedules' => $data->getEmergencySchedulesForVendor($vendor->id),
        ])->all();
    }
}
