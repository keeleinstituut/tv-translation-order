<?php

namespace App\Http\Controllers\API;

use App\Enums\CalendarRole;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\CalendarSearchRequest;
use App\Http\Resources\API\CalendarSearchSlotResource;
use App\Models\VendorCalendarEntry;
use App\Services\Calendar\CalendarDataLoader;
use App\Services\Calendar\CalendarRoleResolver;
use App\Services\Calendar\VendorsAvailabilityService;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CalendarSearchController extends Controller
{
    private const int MAX_SEARCH_DAYS = 30;
    private const int DEFAULT_DURATION_MINUTES = 60;

    public function __construct(
        private readonly CalendarDataLoader         $dataLoader,
        private readonly VendorsAvailabilityService $availabilityService,
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
        path: '/calendar/search',
        description: 'Searches forward in time (up to 30 days) for the first available calendar slot matching the given language and optional duration. Client role: vendor_ids omitted. TPM role: vendor_ids included.',
        summary: 'Find first available calendar slot',
        tags: ['Calendar'],
        parameters: [
            new OA\QueryParameter(name: 'language_id', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\QueryParameter(name: 'datetime', schema: new OA\Schema(type: 'string', format: 'date-time', nullable: true)),
            new OA\QueryParameter(name: 'duration_minutes', schema: new OA\Schema(type: 'integer', minimum: 15, maximum: 480, nullable: true)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'First available slot or empty result if none found within 30 days.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', ref: CalendarSearchSlotResource::class),
            ]
        )
    )]
    public function search(CalendarSearchRequest $request): CalendarSearchSlotResource
    {
        $this->authorize('viewAny', VendorCalendarEntry::class);

        $role = $this->roleResolver->resolve();

        if (!in_array($role, [CalendarRole::Client, CalendarRole::ProjectManager], true)) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Invalid role');
        }

        $languageId = $request->validated('language_id');
        $searchFrom = Carbon::parse($request->validated('datetime') ?? now())->utc();
        $durationMinutes = (int)($request->validated('duration_minutes') ?? self::DEFAULT_DURATION_MINUTES);

        $result = $this->findFirstAvailableSlot(
            $this->roleResolver->getInstitutionId(),
            $languageId,
            $searchFrom,
            $durationMinutes,
            $role,
        );

        return CalendarSearchSlotResource::make($result ?? [
            'start_at' => null,
            'end_at' => null,
            'vendor_ids' => null,
            'language_id' => null,
            'role' => $role,
        ]);
    }

    /**
     * @return array{start_at: string, end_at: string, vendor_ids: string[], language_id: string, role: CalendarRole}|null
     */
    private function findFirstAvailableSlot(
        string       $institutionId,
        string       $languageId,
        Carbon       $searchFrom,
        int          $durationMinutes,
        CalendarRole $role,
    ): ?array
    {
        $searchStart = $searchFrom->copy()->startOfDay();
        $searchEnd = $searchFrom->copy()->addDays(self::MAX_SEARCH_DAYS);

        $data = $this->dataLoader->loadFull(
            $institutionId,
            $searchStart->copy()->startOfDay()->utc(),
            $searchEnd->copy()->endOfDay()->utc(),
        );

        if ($data->importedCalendarVendorIds->isEmpty()) {
            return null;
        }

        $excludeVendorIds = $role === CalendarRole::Client
            ? $data->vendorIdsWithEmergencySchedule()
            : null;

        $vendorFreeIntervals = $this->availabilityService->computeFreeIntervalsForLanguageInRange(
            $data,
            $searchStart->copy()->startOfDay()->utc(),
            $searchEnd->copy()->endOfDay()->utc(),
            $languageId,
            $excludeVendorIds,
        );

        if (empty($vendorFreeIntervals)) {
            return null;
        }

        $durationSeconds = $durationMinutes * 60;
        $searchFromTs = $searchFrom->timestamp;

        // Find the earliest slot of sufficient duration across all vendors
        $earliestSlotStart = null;

        foreach ($vendorFreeIntervals as $intervals) {
            foreach ($intervals as [$start, $end]) {
                $effectiveStart = max($start, $searchFromTs);
                if (($end - $effectiveStart) >= $durationSeconds) {
                    if ($earliestSlotStart === null || $effectiveStart < $earliestSlotStart) {
                        $earliestSlotStart = $effectiveStart;
                    }
                    break;
                }
            }
        }

        if ($earliestSlotStart === null) {
            return null;
        }

        $slotEnd = $earliestSlotStart + $durationSeconds;

        // Collect vendor IDs available for this slot
        $availableVendorIds = [];
        foreach ($vendorFreeIntervals as $vendorId => $intervals) {
            foreach ($intervals as [$start, $end]) {
                if ($start <= $earliestSlotStart && $end >= $slotEnd) {
                    $availableVendorIds[] = $vendorId;
                    break;
                }
            }
        }

        return [
            'start_at' => Carbon::createFromTimestamp($earliestSlotStart)->utc()->toIso8601String(),
            'end_at' => Carbon::createFromTimestamp($slotEnd)->utc()->toIso8601String(),
            'vendor_ids' => $availableVendorIds,
            'language_id' => $languageId,
            'role' => $role,
        ];
    }
}
