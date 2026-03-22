<?php

namespace App\Http\Controllers\API;

use App\Enums\CalendarRole;
use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\DayCalendarRequest;
use App\Http\Resources\API\CalendarClientDayResource;
use App\Http\Resources\API\CalendarDayProjectManagerResource;
use App\Http\Resources\API\VendorCalendarDayResource;
use App\Models\Project;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Policies\ProjectPolicy;
use App\Policies\VendorCalendarEntryPolicy;
use App\Repositories\Calendar\CalendarVendorRepository;
use App\Services\Calendar\CalendarData;
use App\Services\Calendar\CalendarDataLoader;
use App\Services\Calendar\CalendarRoleResolver;
use App\Services\Calendar\SlotDiscretizationService;
use App\Services\Calendar\VendorsAvailabilityService;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CalendarDayController extends Controller
{
    public function __construct(
        private readonly CalendarDataLoader         $dataLoader,
        private readonly VendorsAvailabilityService $availabilityService,
        private readonly SlotDiscretizationService  $discretizationService,
        private readonly CalendarVendorRepository   $vendorRepo,
        private readonly CalendarRoleResolver       $roleResolver,
        AuditLogPublisher                           $auditLogPublisher,
    )
    {
        parent::__construct($auditLogPublisher);
    }

    #[OA\Get(
        path: '/calendar/day',
        description: 'Vendor: own calendar entries for the day. Client: 1h language-tagged available/booked slot grid, client-visible calendar entries, and unassigned projects. TPM: 1h slots with available vendor IDs plus full per-vendor metadata (entries, languages, emergency schedules).',
        summary: 'Day view — response shape varies by acting user role',
        tags: ['Calendar'],
        parameters: [
            new OA\QueryParameter(name: 'date', schema: new OA\Schema(type: 'string', format: 'date', nullable: true)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Response depends on acting user role.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    oneOf: [
                        new OA\Schema(ref: VendorCalendarDayResource::class, description: 'Vendor: own timeline (calendar_entries list)'),
                        new OA\Schema(ref: CalendarClientDayResource::class, description: 'Client: language-tagged 1h available/booked slot grid, calendar entries, and unassigned projects'),
                        new OA\Schema(ref: CalendarDayProjectManagerResource::class, description: 'TPM: 1h slots with vendor_ids plus per-vendor metadata map'),
                    ]
                ),
            ]
        )
    )]
    public function index(DayCalendarRequest $request): JsonResource
    {
        $date = Carbon::parse($request->date ?? today());

        return match ($this->roleResolver->resolve()) {
            CalendarRole::ProjectManager => $this->projectManagerView(
                $this->roleResolver->getInstitutionId(),
                $date,
            ),
            CalendarRole::Client => $this->clientView(
                $this->roleResolver->getInstitutionId(),
                $this->roleResolver->getInstitutionUserId(),
                $date,
            ),
            CalendarRole::Vendor => $this->vendorView(
                $this->roleResolver->getVendor(),
                $date
            ),
            CalendarRole::Unknown => throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid role')
        };
    }

    private function vendorView(Vendor $vendor, Carbon $date): VendorCalendarDayResource
    {
        $dayStart = $date->copy()->startOfDay()->utc();
        $dayEnd = $date->copy()->endOfDay()->utc();

        return VendorCalendarDayResource::make([
            'calendar_entries' => $this->getEntriesForVendorWithRelations(
                $vendor->id,
                $dayStart,
                $dayEnd
            ),
        ]);
    }

    public function getEntriesForVendorWithRelations(string $vendorId, Carbon $start, Carbon $end): Collection
    {
        return VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
            ->where('vendor_id', $vendorId)
            ->with([
                'assignment.subProject.sourceLanguageClassifierValue',
                'assignment.subProject.destinationLanguageClassifierValue',
            ])
            ->overlapping($start, $end)
            ->withoutPrebooked()
            ->orderBy('start_at')
            ->get();
    }

    private function clientView(string $institutionId, string $actingUserId, Carbon $date): CalendarClientDayResource
    {
        $unassignedProjects = $this->getClientUnassignedProjects($actingUserId, $date);

        $dayStart = $date->copy()->startOfDay()->utc();
        $dayEnd = $date->copy()->endOfDay()->utc();

        $data = $this->dataLoader->loadWithEntries(
            $institutionId,
            $dayStart,
            $dayEnd
        );

        if ($data->importedCalendarVendorIds->isEmpty()) {
            return CalendarClientDayResource::make([
                'unassigned_projects' => $unassignedProjects,
            ]);
        }

        $excludeVendorIds = $data->vendorIdsWithEmergencySchedule();
        $vendorWindows = $this->availabilityService->computeVendorWindows($data, $date, $excludeVendorIds);
        $vendorFreeIntervals = $this->availabilityService->computeFreeIntervals($data, $date, $vendorWindows, $excludeVendorIds);
        $perLanguageIntervals = $this->discretizationService->fanOutByLanguage($vendorFreeIntervals, $data);
        $availableSlots = $this->discretizationService->discretizeLanguageSlots($perLanguageIntervals);
        $bookedSlots = $this->discretizationService->computeFullyBookedSlots($availableSlots, $data->coverageByLanguage, $vendorWindows);

        $calendarEntries = $this->getEntriesForClientWithRelations(
            $data->importedCalendarVendorIds, $actingUserId, $dayStart, $dayEnd
        );

        return CalendarClientDayResource::make([
            'available_slots' => $availableSlots,
            'booked_slots' => $bookedSlots,
            'calendar_entries' => $calendarEntries,
            'unassigned_projects' => $unassignedProjects,
        ]);
    }

    /**
     * Entries for client day view, scoped to client with assignment relations.
     *
     * @param Collection<int, string> $vendorIds
     * @return Collection<int, VendorCalendarEntry>
     */
    private function getEntriesForClientWithRelations(Collection $vendorIds, string $clientUserId, Carbon $start, Carbon $end): Collection
    {
        return VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
            ->whereIn('vendor_id', $vendorIds)
            ->overlapping($start, $end)
            ->forClient($clientUserId)
            ->with([
                'assignment.subProject.sourceLanguageClassifierValue',
                'assignment.subProject.destinationLanguageClassifierValue',
                'assignment.subProject.project',
            ])
            ->orderBy('start_at')
            ->get();
    }

    private function projectManagerView(string $institutionId, Carbon $date): CalendarDayProjectManagerResource
    {
        $dayStart = $date->copy()->startOfDay()->utc();
        $dayEnd = $date->copy()->endOfDay()->utc();
        $data = $this->dataLoader->loadWithEntries($institutionId, $dayStart, $dayEnd);

        if ($data->importedCalendarVendorIds->isEmpty()) {
            return CalendarDayProjectManagerResource::make([
                'available_slots' => [],
                'vendors' => [],
            ]);
        }

        $vendorFreeIntervals = $this->availabilityService->computeFreeIntervals($data, $date);
        $availableSlots = $this->discretizationService->discretizeWithVendorIds($vendorFreeIntervals);
        $vendors = $this->buildVendorsMap($data, $dayStart, $dayEnd);

        return CalendarDayProjectManagerResource::make([
            'available_slots' => $availableSlots,
            'vendors' => $vendors,
        ]);
    }

    private function getClientUnassignedProjects(string $institutionUserId, Carbon $date): Collection
    {
        return Project::withGlobalScope('policy', ProjectPolicy::scope())
            ->where('client_institution_user_id', $institutionUserId)
            ->where('is_calendar_project', true)
            ->whereNotNull('event_start_at')
            ->whereNotNull('event_end_at')
            ->whereDate('event_start_at', $date)
            ->whereNot('status', ProjectStatus::Cancelled)
            ->whereDoesntHave('subProjects.assignments.calendarEntry')
            ->with('subProjects')
            ->get();
    }

    /**
     * @return array<int, array>
     */
    private function buildVendorsMap(CalendarData $data, Carbon $dayStart, Carbon $dayEnd): array
    {
        $vendors = $this->vendorRepo->getVendorsWithInstitutionUser($data->allVendorIds);
        $entries = $this->getMinimalEntriesForVendors($data->allVendorIds, $dayStart, $dayEnd);

        return $vendors->map(fn(Vendor $vendor, string $vendorId) => [
            'id' => $vendorId,
            'institutionUser' => $vendor->institutionUser,
            'calendar_entries' => $entries->get($vendorId, collect()),
            'languages' => $data->getLanguagesForVendor($vendorId)->all(),
            'emergency_schedules' => $data->getEmergencySchedulesForVendor($vendorId),
        ])->values()->all();
    }

    private function getMinimalEntriesForVendors(Collection $vendorIds, Carbon $start, Carbon $end): Collection
    {
        if ($vendorIds->isEmpty()) {
            return collect();
        }

        return VendorCalendarEntry::whereIn('vendor_id', $vendorIds)
            ->overlapping($start, $end)
            ->get(['id', 'vendor_id', 'start_at', 'end_at', 'assignment_id', 'prebook_institution_user_id', 'vendor_calendar_import_id'])
            ->groupBy('vendor_id');
    }
}
