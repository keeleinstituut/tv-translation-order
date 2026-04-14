<?php

namespace App\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Enums\VendorCalendarEntryType;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\VendorCalendarEntryIndexRequest;
use App\Http\Requests\API\VendorCalendarEntryStoreRequest;
use App\Http\Resources\API\VendorCalendarEntryResource;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Policies\VendorCalendarEntryPolicy;
use App\Policies\VendorPolicy;
use App\Repositories\Calendar\VendorLanguageCoverageRepository;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class VendorCalendarEntryController extends Controller
{
    const int DEFAULT_PAGE_SIZE = 1000;

    public function __construct(
        private readonly VendorLanguageCoverageRepository $languageCoverage,
        AuditLogPublisher                                 $auditLogPublisher,
    )
    {
        parent::__construct($auditLogPublisher);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/calendar/vendor-entries',
        summary: 'List vendor calendar entries within a date range, scoped by caller role',
        tags: ['Calendar'],
        parameters: [
            new OA\QueryParameter(name: 'date_from', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'date_to', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\QueryParameter(name: 'assignments_only', required: false, schema: new OA\Schema(type: 'boolean')),
            new OA\QueryParameter(name: 'type', required: false, schema: new OA\Schema(type: 'string', enum: VendorCalendarEntryType::class, nullable: true)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorCalendarEntryResource::class, description: 'Vendor calendar entries in the date range')]
    public function index(VendorCalendarEntryIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VendorCalendarEntry::class);

        $institutionUserId = Auth::user()->institutionUserId;
        $institutionId = Auth::user()->institutionId;

        $dateFrom = Carbon::parse($request->validated('date_from'))->startOfDay()->utc();
        $dateTo = Carbon::parse($request->validated('date_to'))->endOfDay()->utc();

        $query = $this->getBaseQuery($institutionUserId, $institutionId)
            ->overlapping($dateFrom, $dateTo);

        if ($type = $request->validated('type')) {
            $query->where(function (Builder $q) use ($type) {
                match (VendorCalendarEntryType::from($type)) {
                    VendorCalendarEntryType::Assignment => $q->whereNotNull('assignment_id'),
                    VendorCalendarEntryType::Prebook => $q->whereNotNull('prebook_institution_user_id'),
                    VendorCalendarEntryType::ExternalCalendar => $q->whereNotNull('vendor_calendar_import_id'),
                    VendorCalendarEntryType::Absence => $q->whereNotNull('absence_creator_institution_user_id'),
                    VendorCalendarEntryType::Vacation => $q->whereNotNull('institution_user_vacation_id')->orWhereNotNull('institution_vacation_id'),
                };
            });
        } elseif ($request->boolean('assignments_only', true)) {
            $query->assignmentsOnly();
        }

        $entries = $query
            ->with([
                'assignment.assignee',
                'assignment.subProject.sourceLanguageClassifierValue',
                'assignment.subProject.destinationLanguageClassifierValue',
                'assignment.subProject.project',
            ])
            ->orderBy('start_at')
            ->paginate(self::DEFAULT_PAGE_SIZE);

        return VendorCalendarEntryResource::collection($entries);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/calendar/vendor-entries',
        summary: 'Create a vendor absence entry (out-of-office)',
        requestBody: new OAH\RequestBody(VendorCalendarEntryStoreRequest::class),
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: VendorCalendarEntryResource::class, description: 'Created absence entry', response: Response::HTTP_CREATED)]
    public function store(VendorCalendarEntryStoreRequest $request): VendorCalendarEntryResource
    {
        $this->authorize('create', VendorCalendarEntry::class);

        $vendor = Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->findOrFail($request->validated('vendor_id'));

        $entry = VendorCalendarEntry::create([
            'vendor_id' => $vendor->id,
            'start_at' => Carbon::parse($request->validated('start_at'))->utc(),
            'end_at' => Carbon::parse($request->validated('end_at'))->utc(),
            'absence_creator_institution_user_id' => Auth::user()->institutionUserId,
            'metadata' => $request->validated('comment') ? ['comment' => $request->validated('comment')] : null,
        ]);

        return VendorCalendarEntryResource::make($entry);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Delete(
        path: '/calendar/vendor-entries/{entry}',
        summary: 'Delete a vendor calendar entry',
        tags: ['Calendar'],
        parameters: [new OAH\UuidPath('entry')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Entry deleted')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Only external calendar or absence entries can be deleted')]
    public function destroy(Request $request): Response
    {
        $entry = VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
            ->findOrFail($request->route('entry'));

        $this->authorize('delete', $entry);

        if (! in_array($entry->type, [VendorCalendarEntryType::ExternalCalendar, VendorCalendarEntryType::Absence])) {
            abort(Response::HTTP_BAD_REQUEST, 'Kustutada saab ainult väliseid kalendrikirjeid ja eemalolekuaegu');
        }

        $entry->delete();

        return response()->noContent();
    }

    /**
     * @return Builder<VendorCalendarEntry>
     */
    private function getBaseQuery(string $institutionUserId, string $institutionId): Builder
    {
        $query = VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope());

        if (Auth::hasPrivilege(PrivilegeKey::ReceiveProject->value) ||
            Auth::hasPrivilege(PrivilegeKey::ManageProject->value)) {
            $vendorIds = $this->languageCoverage
                ->getCoverageForInstitution($institutionId)
                ->pluck('vendor_id')
                ->unique()
                ->all();

            return $query->whereIn('vendor_id', $vendorIds);
        }

        if (Auth::hasPrivilege(PrivilegeKey::CreateProject->value)) {
            return $query->forClient($institutionUserId);
        }

        $vendor = Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', $institutionUserId)
            ->first();
        if ($vendor) {
            return $query->where('vendor_id', $vendor->id);
        }


        return $query->whereRaw('0=1');
    }
}
