<?php

namespace App\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\VendorCalendarEntryIndexRequest;
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

        if ($request->boolean('assignments_only', true)) {
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
    #[OA\Delete(
        path: '/calendar/vendor-entries/{entry}',
        summary: 'Delete a vendor calendar entry',
        tags: ['Calendar'],
        parameters: [new OAH\UuidPath('entry')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Entry deleted')]
    public function destroy(Request $request): Response
    {
        $entry = VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope())
            ->findOrFail($request->route('entry'));

        $this->authorize('delete', $entry);

        $entry->delete();

        return response()->noContent();
    }

    /**
     * @return Builder<VendorCalendarEntry>
     */
    private function getBaseQuery(string $institutionUserId, string $institutionId): Builder
    {
        $query = VendorCalendarEntry::withGlobalScope('policy', VendorCalendarEntryPolicy::scope());

        if (Auth::hasPrivilege(PrivilegeKey::ManageProject->value)) {
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
