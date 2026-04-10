<?php

namespace App\Http\Controllers\API;

use App\Exceptions\CalendarSlotConflictException;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\PrebookRequest;
use App\Http\Resources\API\PrebookResource;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Services\Calendar\SlotMatchingService;
use App\Services\Calendar\TimeSlot;
use App\Services\Calendar\VendorReservationService;
use AuditLogClient\Services\AuditLogPublisher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CalendarPrebookController extends Controller
{
    public function __construct(
        private readonly SlotMatchingService        $slotMatching,
        private readonly VendorReservationService   $vendorReservation,
        AuditLogPublisher                           $auditLogPublisher,
    )
    {
        parent::__construct($auditLogPublisher);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/calendar/prebook',
        summary: 'Create a prebook for the best available internal vendor',
        requestBody: new OAH\RequestBody(PrebookRequest::class),
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: PrebookResource::class, description: 'Prebook created')]
    public function prebook(PrebookRequest $request): \Illuminate\Http\Response|PrebookResource
    {
        $this->authorize('prebook', VendorCalendarEntry::class);

        $slotStart = Carbon::parse($request->validated('start_at'))->utc();
        $slotEnd = Carbon::parse($request->validated('end_at'))->utc();
        $languageId = $request->validated('language_id');
        $tagIds = collect($request->validated('tag_ids', []));
        $vendorId = $request->validated('vendor_id');

        $institutionUserId = Auth::user()->institutionUserId;
        $institutionId = Auth::user()->institutionId;

        return DB::transaction(function () use ($slotStart, $slotEnd, $languageId, $tagIds, $vendorId, $institutionUserId, $institutionId) {
            $prebook = VendorCalendarEntry::where('prebook_institution_user_id', $institutionUserId)->first();

            if (filled($prebook)) {
                $prebook->forceDelete();
            }

            $vendor = filled($vendorId) ?
                Vendor::find($vendorId) :
                $this->slotMatching->pickBestInternalVendor(
                    $languageId,
                    $slotStart,
                    $slotEnd,
                    $institutionId,
                    $tagIds,
                );

            if (blank($vendor)) {
                return response()->noContent();
            }

            $slot = TimeSlot::forEvent($slotStart, $slotEnd);
            if (!$this->slotMatching->isVendorAvailableForSlot($vendor, $slot, $institutionId)) {
                throw new CalendarSlotConflictException();
            }

            $calendarEntry = $this->vendorReservation->prebook($vendor->id, $slotStart, $slotEnd, $institutionUserId);

            return PrebookResource::make([
                'calendar_entry' => $calendarEntry,
                'expires_at' => $this->vendorReservation->getPrebookExpiresAt($calendarEntry),
            ]);
        });
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Delete(
        path: '/calendar/prebook',
        summary: 'Cancel the active prebook for the current user',
        tags: ['Calendar'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Prebook cancelled')]
    public function cancelPrebook(): Response
    {
        $this->authorize('prebook', VendorCalendarEntry::class);

        $institutionUserId = Auth::user()->institutionUserId;

        $this->vendorReservation->releasePrebook($institutionUserId);

        return response()->noContent();
    }
}
