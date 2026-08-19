<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\VendorEmergencyScheduleCreateRequest;
use App\Http\Resources\API\VendorEmergencyScheduleResource;
use App\Models\Vendor;
use App\Models\VendorEmergencySchedule;
use App\Policies\VendorEmergencySchedulePolicy;
use App\Policies\VendorPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VendorEmergencyScheduleController extends Controller
{
    /**
     * @throws Throwable
     */
    #[OA\Get(
        path: '/vendors/{vendor}/emergency-schedules',
        summary: 'List emergency schedules for a vendor',
        tags: ['Calendar'],
        parameters: [new OAH\UuidPath('vendor')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OAH\CollectionResponse(itemsRef: VendorEmergencyScheduleResource::class, description: 'List of emergency schedules')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', VendorEmergencySchedule::class);

        $schedules = self::getBaseQuery()
            ->where('vendor_id', $request->route('vendor'))
            ->orderBy('start_date')
            ->get();

        return VendorEmergencyScheduleResource::collection($schedules);
    }

    /**
     * @throws Throwable
     */
    #[OA\Post(
        path: '/vendors/{vendor}/emergency-schedules',
        summary: 'Create an emergency schedule for a vendor',
        requestBody: new OAH\RequestBody(VendorEmergencyScheduleCreateRequest::class),
        tags: ['Calendar'],
        parameters: [new OAH\UuidPath('vendor')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: VendorEmergencyScheduleResource::class, description: 'Created emergency schedule', response: Response::HTTP_CREATED)]
    public function store(VendorEmergencyScheduleCreateRequest $request): VendorEmergencyScheduleResource
    {
        $this->authorize('create', VendorEmergencySchedule::class);

        Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->findOrFail($request->route('vendor'));

        $schedule = (new VendorEmergencySchedule)->fill([
            'vendor_id' => $request->route('vendor'),
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->validated('end_date'),
        ]);
        $schedule->saveOrFail();

        return VendorEmergencyScheduleResource::make($schedule->refresh());
    }

    /**
     * @throws Throwable
     */
    #[OA\Delete(
        path: '/vendors/{vendor}/emergency-schedules/{emergency_schedule}',
        summary: 'Delete an emergency schedule',
        tags: ['Calendar'],
        parameters: [new OAH\UuidPath('vendor'), new OAH\UuidPath('emergency_schedule')],
        responses: [new OAH\Forbidden, new OAH\Unauthorized]
    )]
    #[OA\Response(response: Response::HTTP_NO_CONTENT, description: 'Emergency schedule deleted')]
    public function destroy(Request $request): Response
    {
        $schedule = self::getBaseQuery()
            ->where('vendor_id', $request->route('vendor'))
            ->findOrFail($request->route('emergency_schedule'));

        $this->authorize('delete', $schedule);

        $schedule->deleteOrFail();

        return response()->noContent();
    }

    private static function getBaseQuery(): Builder|VendorEmergencySchedule
    {
        return VendorEmergencySchedule::withGlobalScope('policy', VendorEmergencySchedulePolicy::scope());
    }
}
