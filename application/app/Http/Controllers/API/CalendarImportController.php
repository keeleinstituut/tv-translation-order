<?php

namespace App\Http\Controllers\API;

use App\Exceptions\CalendarSlotConflictException;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\CalendarImportRequest;
use App\Http\Requests\API\VendorCalendarImportIndexRequest;
use App\Http\Resources\API\VendorCalendarImportResource;
use App\Models\Vendor;
use App\Models\VendorCalendarImport;
use App\Policies\VendorPolicy;
use App\Services\Calendar\VendorReservationService;
use AuditLogClient\Services\AuditLogPublisher;
use ICal\ICal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CalendarImportController extends Controller
{
    const int DEFAULT_PAGE_SIZE = 100;

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
    #[OA\Get(
        path: '/calendar/import',
        summary: 'List the authenticated vendor\'s calendar imports, optionally filtered to those overlapping a date range',
        tags: ['Calendar'],
        parameters: [
            new OA\QueryParameter(name: 'date_from', required: false, schema: new OA\Schema(type: 'string', format: 'date', nullable: true)),
            new OA\QueryParameter(name: 'date_to', required: false, schema: new OA\Schema(type: 'string', format: 'date', nullable: true)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorCalendarImportResource::class, description: 'Vendor calendar imports')]
    public function index(VendorCalendarImportIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VendorCalendarImport::class);

        $vendor = Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', Auth::user()->institutionUserId)
            ->firstOrFail();

        $query = VendorCalendarImport::query()
            ->where('vendor_id', $vendor->id)
            ->withCount('events')
            ->orderByDesc('date_to');

        if ($dateFrom = $request->validated('date_from')) {
            $query->where('date_to', '>=', Carbon::parse($dateFrom)->startOfDay()->utc());
        }

        if ($dateTo = $request->validated('date_to')) {
            $query->where('date_from', '<=', Carbon::parse($dateTo)->endOfDay()->utc());
        }

        return VendorCalendarImportResource::collection($query->paginate(self::DEFAULT_PAGE_SIZE));
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

        $importStartDate = Carbon::today()->utc();
        $importEndDate = Carbon::parse($request->validated('import_end_date'))->endOfDay()->utc();

        $eventsSource = new ICal($request->file('file')->getRealPath(), [
            'filterDaysBefore' => $importStartDate->toDateTime(),
            'filterDaysAfter' => $importEndDate->toDateTime(),
        ]);

        $events = $eventsSource->events();

        $import = DB::transaction(function () use ($vendor, $importStartDate, $importEndDate, $events) {
            $import = VendorCalendarImport::create([
                'vendor_id' => $vendor->id,
                'date_from' => $importStartDate,
                'date_to' => $importEndDate,
            ]);

            collect($events)
                ->filter(fn($event) => !empty($event->dtstart) && !empty($event->dtend))
                ->each(function ($event) use ($vendor, $import) {
                    try {
                        $this->vendorReservation->createCalendarEntryWithConflictHandling([
                            'vendor_id' => $vendor->id,
                            'start_at' => Carbon::createFromTimestamp($event->dtstart_array[2]),
                            'end_at' => Carbon::createFromTimestamp($event->dtend_array[2]),
                            'vendor_calendar_import_id' => $import->id,
                            'metadata' => json_encode([
                                'summary' => $event->summary ?? null,
                                'description' => $event->description ?? null,
                            ])
                        ]);
                    } catch (CalendarSlotConflictException) {}
                });

            return $import;
        });

        return VendorCalendarImportResource::make($import->loadCount('events'));
    }

    /**
     * @throws Throwable
     */
    #[OA\Delete(
        path: '/calendar/import/bulk',
        summary: 'Delete all of the authenticated vendor\'s calendar imports with end date in the future, along with their entries',
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorCalendarImportResource::class, description: 'Deleted vendor calendar imports')]
    public function bulkDestroy(): \Illuminate\Http\Response
    {
        $vendor = Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', Auth::user()->institutionUserId)
            ->firstOrFail();

        DB::transaction(function () use ($vendor) {
            $imports = VendorCalendarImport::query()
                ->where('vendor_id', $vendor->id)
                ->where('date_to', '>', Carbon::now()->utc())
                ->withCount('events')
                ->get();

            $imports->each(function (VendorCalendarImport $import): void {
                $this->authorize('delete', $import);
                $import->deleteOrFail();
            });
        });

        return response()->noContent();
    }
}
