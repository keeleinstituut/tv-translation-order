<?php

namespace App\Http\Controllers\API;

use App\Enums\CalendarRole;
use App\Helpers\IntervalsUtil;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\WeekAggregationRequest;
use App\Http\Resources\API\CalendarWeekProjectManagerResource;
use App\Http\Resources\API\ClientCalendarWeekSlotsResource;
use App\Http\Resources\API\VendorCalendarWeekSlotsResource;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Http\Resources\API\VendorCalendarEntryResource;
use App\Policies\VendorCalendarEntryPolicy;
use App\Repositories\Calendar\CalendarVendorRepository;
use App\Services\Calendar\CalendarData;
use App\Services\Calendar\CalendarDataLoader;
use App\Services\Calendar\CalendarRoleResolver;
use App\Services\Calendar\SlotDiscretizationService;
use App\Services\Calendar\VendorsAvailabilityService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use AuditLogClient\Services\AuditLogPublisher;
use App\Http\OpenApiHelpers as OAH;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CalendarWeekController extends Controller
{
    public function __construct(
        private readonly CalendarDataLoader         $dataLoader,
        private readonly VendorsAvailabilityService $availabilityService,
        private readonly SlotDiscretizationService  $discretizationService,
        private readonly CalendarVendorRepository   $vendorRepo,
        private readonly CalendarRoleResolver       $roleResolver,
        AuditLogPublisher                           $auditLogPublisher,
    ) {
        parent::__construct($auditLogPublisher);
    }

    #[OA\Get(
        path: '/calendar/week',
        description: 'Vendor: own booked slots grouped by language and 6h slot. Client: vendor count aggregates per language/slot (vendors with active emergency schedules excluded). TPM: available vendor IDs per language/slot plus vendor metadata.',
        summary: 'Week view — 6-hour slot availability; response shape varies by caller role',
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
                        new OA\Schema(ref: VendorCalendarWeekSlotsResource::class, description: 'Vendor: booked slots with calendar entries per 6h slot'),
                        new OA\Schema(ref: ClientCalendarWeekSlotsResource::class, description: 'Client: total/available vendor counts per language per 6h slot'),
                        new OA\Schema(ref: CalendarWeekProjectManagerResource::class, description: 'TPM: available vendor IDs per language/slot plus vendor metadata map'),
                    ]
                ),
            ]
        )
    )]
    public function index(WeekAggregationRequest $request): JsonResource
    {
        $startAt = Carbon::parse($request->validated('date_from'))->startOfDay()->utc();
        $endAt = Carbon::parse($request->validated('date_to'))->endOfDay()->utc();

        return match ($this->roleResolver->resolve()) {
            CalendarRole::ProjectManager => $this->projectManagerView($this->roleResolver->getInstitutionId(), $startAt, $endAt),
            CalendarRole::Vendor => $this->vendorView($this->roleResolver->getVendor(), $startAt, $endAt),
            CalendarRole::Client => $this->clientView($this->roleResolver->getInstitutionId(), $startAt, $endAt),
            CalendarRole::Unknown => throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid role')
        };
    }

    private function vendorView(Vendor $vendor, Carbon $startAt, Carbon $endAt): VendorCalendarWeekSlotsResource
    {
        $entries = VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
            ->where('vendor_id', $vendor->id)
            ->assignmentsOnly()
            ->overlapping($startAt, $endAt)
            ->with(['assignment.subProject.project'])
            ->orderBy('start_at')
            ->get();

        if ($entries->isEmpty()) {
            return VendorCalendarWeekSlotsResource::make(['slots' => collect()]);
        }

        $slots = IntervalsUtil::generateSlots($startAt, $endAt);
        $cursor = 0;
        $results = collect();

        foreach ($slots as $slotStart) {
            $slotEnd = $slotStart->copy()->addHours(6);
            $slotStartTs = $slotStart->timestamp;
            $slotEndTs = $slotEnd->timestamp;

            while ($cursor < $entries->count() && $entries->get($cursor)->end_at->timestamp <= $slotStartTs) {
                $cursor++;
            }

            $slotEntries = collect();
            for ($i = $cursor; $i < $entries->count(); $i++) {
                if ($entries->get($i)->start_at->timestamp >= $slotEndTs) {
                    break;
                }
                $slotEntries->add($entries[$i]);
            }

            if ($slotEntries->isEmpty()) {
                continue;
            }

            $groupedByLanguage = $slotEntries->groupBy(
                fn($e) => $e->assignment->subProject->destination_language_classifier_value_id
            );

            foreach ($groupedByLanguage as $languageId => $languageEntries) {
                $results->push([
                    'language_id' => $languageId,
                    'start_at' => $slotStart->toIso8601String(),
                    'end_at' => $slotEnd->toIso8601String(),
                    'calendar_entries' => VendorCalendarEntryResource::collection($languageEntries),
                ]);
            }
        }

        return VendorCalendarWeekSlotsResource::make([
            'slots' => $results->sortBy(['language_id', 'start_at'])->values(),
        ]);
    }

    private function clientView(string $institutionId, Carbon $startAt, Carbon $endAt): ClientCalendarWeekSlotsResource
    {
        $data = $this->dataLoader->loadWithEntries($institutionId, $startAt, $endAt);

        if ($data->importedCalendarVendorIds->isEmpty()) {
            return ClientCalendarWeekSlotsResource::make(['slots' => collect()]);
        }

        $slots = IntervalsUtil::generateSlots($startAt, $endAt);
        $precomputedAvailability = $this->availabilityService->computeSlotAvailability($data, $slots, excludeEmergency: true);

        $results = collect();
        $now = Carbon::now();
        foreach ($slots as $slotIndex => $slotStart) {
            $slotEnd = $slotStart->copy()->addHours(6);
            if ($slotEnd <  $now) {
                continue;
            }

            $results = $results->merge(
                $this->discretizationService->computeSlotLanguageAvailability(
                    $data->coverageByLanguage,
                    $precomputedAvailability,
                    $slotStart,
                    $slotEnd,
                    $slotIndex,
                    $institutionId,
                )
            );
        }

        return ClientCalendarWeekSlotsResource::make([
            'slots' => $results
                ->map(fn($item) => [
                    'language_id' => $item['language_id'],
                    'start_at' => $item['slot_start'],
                    'end_at' => $item['slot_end'],
                    'total_vendors' => $item['total_vendors'],
                    'available_vendors' => $item['available_vendors'],
                ])
                ->sortBy(['language_id', 'start_at'])
                ->values(),
        ]);
    }

    private function projectManagerView(string $institutionId, Carbon $startAt, Carbon $endAt): CalendarWeekProjectManagerResource
    {
        $data = $this->dataLoader->loadWithEntries($institutionId, $startAt, $endAt);

        if ($data->importedCalendarVendorIds->isEmpty()) {
            return CalendarWeekProjectManagerResource::make([
                'available_slots' => [],
                'vendors' => [],
            ]);
        }

        $slots = IntervalsUtil::generateSlots($startAt, $endAt);
        $precomputedAvailability = $this->availabilityService->computeSlotAvailability($data, $slots);

        $results = collect();
        $now = Carbon::now();
        foreach ($slots as $slotIndex => $slotStart) {
            $slotEnd = $slotStart->copy()->addHours(6);
            if ($slotEnd <  $now) {
                continue;
            }

            $results = $results->merge(
                $this->discretizationService->computeSlotLanguageAvailability(
                    $data->coverageByLanguage,
                    $precomputedAvailability,
                    $slotStart,
                    $slotEnd,
                    $slotIndex,
                    $institutionId,
                )
            );
        }

        $availableSlots = $results
            ->map(fn($item) => [
                'language_id' => $item['language_id'],
                'start_at' => $item['slot_start'],
                'end_at' => $item['slot_end'],
                'vendor_ids' => $item['available_vendor_ids'],
            ])
            ->sortBy(['language_id', 'start_at'])
            ->values()
            ->all();

        return CalendarWeekProjectManagerResource::make([
            'available_slots' => $availableSlots,
            'vendors' => $this->buildVendorsMap($data),
        ]);
    }

    /**
     * @return array<int, array>
     */
    private function buildVendorsMap(CalendarData $data): array
    {
        $vendors = $this->vendorRepo->getVendorsWithInstitutionUser($data->allVendorIds);
        $vendorLanguages = $data->getLanguagesByVendor();

        return $vendors->map(fn(Vendor $vendor, string $vendorId) => [
            'id' => $vendorId,
            'institutionUser' => $vendor->institutionUser,
            'languages' => $vendorLanguages[$vendorId] ?? [],
            'emergency_schedules' => $data->getEmergencySchedulesForVendor($vendorId),
        ])->values()->all();
    }
}
