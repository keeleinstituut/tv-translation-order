<?php

namespace App\Http\Controllers\API;

use App\Exceptions\CalendarSlotConflictException;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\CalendarImportRequest;
use App\Http\Resources\API\VendorCalendarImportResource;
use App\Models\Vendor;
use App\Models\VendorCalendarImport;
use App\Policies\VendorPolicy;
use App\Services\Calendar\VendorReservationService;
use AuditLogClient\Services\AuditLogPublisher;
use ICal\ICal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class CalendarImportController extends Controller
{

    public function __construct(
        private readonly VendorReservationService $vendorReservation,
        AuditLogPublisher                         $auditLogPublisher,
    )
    {
        parent::__construct($auditLogPublisher);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/calendar/import',
        summary: 'Import an ICS calendar file for the authenticated vendor',
        requestBody: new OAH\RequestBody(CalendarImportRequest::class),
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(
        dataRef: VendorCalendarImportResource::class,
        description: 'Calendar import created',
        response: Response::HTTP_CREATED
    )]
    public function store(CalendarImportRequest $request): VendorCalendarImportResource
    {
        $this->authorize('create', VendorCalendarImport::class);

        $vendor = Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', Auth::user()->institutionUserId)
            ->firstOrFail();

        $now = Carbon::now()->utc();
        $importEndDate = Carbon::parse($request->validated('import_end_date'))->endOfDay()->utc();

        $eventsSource = new ICal($request->file('file')->getRealPath(), [
            'filterDaysBefore' => $now->startOfDay()->toDateTime(),
            'filterDaysAfter' => $importEndDate->toDateTime(),
        ]);

        $events = $eventsSource->events();

        $import = VendorCalendarImport::create([
            'vendor_id' => $vendor->id,
            'date_from' => $now,
            'date_to' => $importEndDate,
        ]);

        collect($events)
            ->filter(fn($event) => !empty($event->dtstart) && !empty($event->dtend))
            ->each(function ($event) use ($vendor, $import) {
                try {
                    $this->vendorReservation->createCalendarEntryWithConflictHandling([
                        'vendor_id' => $vendor->id,
                        'start_at' => Carbon::parse($event->dtstart)->utc(),
                        'end_at' => Carbon::parse($event->dtend)->utc(),
                        'vendor_calendar_import_id' => $import->id,
                        'metadata' => json_encode([
                            'summary' => $event->summary ?? null,
                            'description' => $event->description ?? null,
                        ])
                    ]);
                } catch (CalendarSlotConflictException) {
                }
            });


        return VendorCalendarImportResource::make($import->loadCount('events'));
    }
}
