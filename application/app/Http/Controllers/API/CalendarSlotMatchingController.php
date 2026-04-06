<?php

namespace App\Http\Controllers\API;

use App\Enums\CalendarRole;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\SlotMatchingVendorsRequest;
use App\Http\Resources\API\VendorResource;
use App\Models\Vendor;
use App\Services\Calendar\CalendarRoleResolver;
use App\Services\Calendar\SlotMatchingService;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CalendarSlotMatchingController extends Controller
{
    public function __construct(
        private readonly CalendarRoleResolver $roleResolver,
        private readonly SlotMatchingService  $slotMatchingService,
        AuditLogPublisher                     $auditLogPublisher,
    ) {
        parent::__construct($auditLogPublisher);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/calendar/slot-matching/vendors',
        description: 'Returns vendors available for a given time slot and language. Internal vendors must have an imported calendar and the slot must fall within their working window. External vendors only need language coverage and no conflicting entries.',
        summary: 'List vendors available for a slot',
        tags: ['Calendar'],
        parameters: [
            new OA\QueryParameter(name: 'language_id', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\QueryParameter(name: 'start_at', required: true, schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\QueryParameter(name: 'end_at', required: true, schema: new OA\Schema(type: 'string', format: 'date-time')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorResource::class)]
    public function vendors(SlotMatchingVendorsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Vendor::class);

        $role = $this->roleResolver->resolve();

        if ($role !== CalendarRole::ProjectManager) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Invalid role');
        }

        $startAt = Carbon::parse($request->validated('start_at'))->utc();
        $endAt = Carbon::parse($request->validated('end_at'))->utc();

        $vendors = $this->slotMatchingService->findAvailableVendorsForSlot(
            $request->validated('language_id'),
            $startAt,
            $endAt,
            $this->roleResolver->getInstitutionId(),
        );

        $vendors->load(['emergencySchedules' => fn($q) => $q
            ->where('start_date', '<=', $endAt->toDateString())
            ->where('end_date', '>=', $startAt->toDateString())
        ]);

        return VendorResource::collection($vendors);
    }
}
