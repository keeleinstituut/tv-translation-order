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
use App\Services\Calendar\CalendarDataLoader;
use App\Services\Calendar\CalendarRoleResolver;
use App\Services\Calendar\SlotDiscretizationService;
use App\Services\Calendar\VendorsAvailabilityService;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Auth\Access\AuthorizationException;
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
        private readonly CalendarRoleResolver       $roleResolver,
        AuditLogPublisher                           $auditLogPublisher,
    )
    {
        parent::__construct($auditLogPublisher);
    }

    /**
     * @throws AuthorizationException
     */
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
        $this->authorize('viewAny', VendorCalendarEntry::class);

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

        $entries = VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
            ->where('vendor_id', $vendor->id)
            ->with([
                'assignment.subProject.sourceLanguageClassifierValue',
                'assignment.subProject.destinationLanguageClassifierValue',
            ])
            ->overlapping($dayStart, $dayEnd)
            ->withoutPrebooked()
            ->orderBy('start_at')
            ->get();

        return VendorCalendarDayResource::make([
            'calendar_entries' => $entries,
        ]);
    }

    private function clientView(string $institutionId, string $actingUserId, Carbon $date): CalendarClientDayResource
    {
        $unassignedProjects = $this->getClientUnassignedProjects($actingUserId, $date);

        $dayStart = $date->copy()->startOfDay()->utc();
        $dayEnd = $date->copy()->endOfDay()->utc();

        $data = $this->dataLoader->loadFull(
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

        $entries = VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
            ->whereIn('vendor_id', $data->importedCalendarVendorIds)
            ->overlapping($dayStart, $dayEnd)
            ->forClient($actingUserId)
            ->with([
                'assignment.subProject.sourceLanguageClassifierValue',
                'assignment.subProject.destinationLanguageClassifierValue',
                'assignment.subProject.project',
            ])
            ->orderBy('start_at')
            ->get();

        return CalendarClientDayResource::make([
            'available_slots' => $availableSlots,
            'booked_slots' => $bookedSlots,
            'calendar_entries' => $entries,
            'unassigned_projects' => $unassignedProjects,
        ]);
    }

    private function projectManagerView(string $institutionId, Carbon $date): CalendarDayProjectManagerResource
    {
        $dayStart = $date->copy()->startOfDay()->utc();
        $dayEnd = $date->copy()->endOfDay()->utc();
        $data = $this->dataLoader->loadFull($institutionId, $dayStart, $dayEnd);

        if ($data->importedCalendarVendorIds->isEmpty()) {
            return CalendarDayProjectManagerResource::make([
                'available_slots' => [],
                'vendors' => [],
            ]);
        }

        $vendorFreeIntervals = $this->availabilityService->computeFreeIntervals($data, $date);
        $availableSlots = $this->discretizationService->discretizeWithVendorIds($vendorFreeIntervals);
        $entriesByVendor = $data->internalVendorIds->isNotEmpty()
            ? VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
                ->whereIn('vendor_id', $data->internalVendorIds)
                ->overlapping($dayStart, $dayEnd)
                ->with([
                    'assignment.subProject.sourceLanguageClassifierValue',
                    'assignment.subProject.destinationLanguageClassifierValue',
                    'assignment.subProject.project',
                ])
                ->orderBy('start_at')
                ->get()
                ->groupBy('vendor_id')
            : collect();

        return CalendarDayProjectManagerResource::make([
            'available_slots' => $availableSlots,
            'vendors' => $data->buildExpandedVendors($entriesByVendor),
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
}
