<?php

namespace App\Http\Controllers\API;

use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\OutsourceRequestAcceptRequest;
use App\Http\Requests\API\OutsourceRequestCancelRequest;
use App\Http\Requests\API\OutsourceRequestCreateRequest;
use App\Http\Requests\API\OutsourceRequestDeclineRequest;
use App\Http\Requests\API\OutsourceRequestListRequest;
use App\Http\Requests\API\OutsourceRequestReorderRequest;
use App\Http\Requests\API\OutsourceRequestSelectRequest;
use App\Http\Resources\API\OutsourceRequestResource;
use App\Models\Assignment;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;
use App\Models\InstitutionPartner;
use App\Policies\OutsourceRequestPolicy;
use App\Services\OutsourceRequest\OutsourceRequestStateMachine;
use App\Jobs\ExpireOutsourceOfferJob;
use App\Services\Prices\OutsourcePartnerPriceCalculator;
use AuditLogClient\Services\AuditLogPublisher;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OutsourceRequestController extends Controller
{
    public function __construct(
        AuditLogPublisher                             $auditLogPublisher,
        private readonly OutsourceRequestStateMachine $stateMachine,
    )
    {
        parent::__construct($auditLogPublisher);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/outsource-requests',
        summary: 'List outsource requests for the current institution',
        tags: ['Outsource requests'],
        parameters: [
            new OA\QueryParameter(name: 'assignment_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'sub_project_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'project_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'status[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', enum: OutsourceRequestStatus::class), nullable: true)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['created_at'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc'])),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: OutsourceRequestResource::class)]
    public function index(OutsourceRequestListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OutsourceRequest::class);

        $params = collect($request->validated());
        $query = $this->getBaseQuery()->with([
            'ownerInstitution',
            'offers.institution',
            'assignment.subProject',
            'assignment.jobDefinition'
        ]);

        if ($param = $params->get('assignment_id')) {
            $query->where('assignment_id', $param);
        }

        if ($param = $params->get('sub_project_id')) {
            $query->whereHas('assignment.subProject', fn(Builder $q) => $q->where('id', $param));
        }

        if ($param = $params->get('project_id')) {
            $query->whereHas('assignment.subProject.project', fn(Builder $q) => $q->where('id', $param));
        }

        if ($param = $params->get('status')) {
            $query->whereIn('status', $param);
        }

        $sortBy = $params->get('sort_by', 'created_at');
        $sortOrder = $params->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        return OutsourceRequestResource::collection(
            $query->paginate($params->get('per_page', 10))
        );
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/outsource-requests/{id}',
        summary: 'Show an outsource request',
        tags: ['Outsource requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: OutsourceRequestResource::class, description: 'Outsource request')]
    public function show(string $id): OutsourceRequestResource
    {
        $this->authorize('viewAny', OutsourceRequest::class);

        /** @var OutsourceRequest $outsourceRequest */
        $outsourceRequest = $this->getBaseQuery()
            ->with([
                'ownerInstitution',
                'assignment.subProject.project.sourceFiles',
                'assignment.subProject.project.helpFiles',
                'assignment.jobDefinition',
                'offers.institution',
                'media'
            ])->findOrFail($id);

        $this->authorize('view', $outsourceRequest);

        return OutsourceRequestResource::make($outsourceRequest);
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Post(
        path: '/outsource-requests',
        summary: 'Create an outsource request',
        requestBody: new OAH\RequestBody(OutsourceRequestCreateRequest::class),
        tags: ['Outsource requests'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: OutsourceRequestResource::class, description: 'Created outsource request', response: Response::HTTP_CREATED)]
    public function store(OutsourceRequestCreateRequest $request): OutsourceRequestResource
    {
        $validated = $request->validated();
        $assignment = Assignment::findOrFail($validated['assignment_id']);
        $institutionUserId = Auth::user()->institutionUserId;
        $institutionId = Auth::user()->institutionId;
        $this->authorize('create', [OutsourceRequest::class, $assignment]);

        $outsourceRequest = DB::transaction(function () use ($validated, $assignment, $institutionUserId, $institutionId) {
            $outsourceRequest = OutsourceRequest::create([
                'assignment_id' => $assignment->id,
                'institution_user_id' => $institutionUserId,
                'mode' => $validated['mode'],
                'reaction_time_minutes' => $validated['reaction_time_minutes'],
                'special_instructions' => $validated['special_instructions'] ?? null,
                'price' => $validated['override_price'] ?? null,
                'include_price' => $validated['include_price'] ?? true,
                'include_source_files' => $validated['include_source_files'] ?? true,
                'status' => OutsourceRequestStatus::Active,
            ]);

            $offerInstitutionIds = collect($validated['recipients'])->pluck('institution_id');
            $partnersByInstitutionId = InstitutionPartner::query()
                ->where('institution_id', $institutionId)
                ->whereIn('partner_institution_id', $offerInstitutionIds)
                ->get()
                ->keyBy('partner_institution_id');

            $isCascade = $outsourceRequest->isCascade();

            foreach ($validated['recipients'] as $index => $row) {
                /** @var InstitutionPartner $partner */
                $partner = $partnersByInstitutionId->get($row['institution_id']);
                $calculatedPrice = new OutsourcePartnerPriceCalculator($assignment, $partner)->getPrice();

                $notified = !$isCascade || $index === 0;

                $offer = $outsourceRequest->offers()->create([
                    'institution_id' => $row['institution_id'],
                    'status' => $notified
                        ? OutsourceOfferStatus::RequestSent
                        : OutsourceOfferStatus::RequestPending,
                    'notified_at' => $notified ? now() : null,
                    'expires_at' => match (true) {
                        $isCascade && $notified => now()->addMinutes($outsourceRequest->reaction_time_minutes),
                        !$isCascade => $outsourceRequest->created_at->copy()->addMinutes($outsourceRequest->reaction_time_minutes),
                        default => null,
                    },
                    'calculated_price' => $calculatedPrice,
                ]);

                if ($notified) {
                    ExpireOutsourceOfferJob::dispatch($offer->id)
                        ->afterCommit()
                        ->delay($offer->expires_at);
                }
            }

            foreach ($validated['request_files'] ?? [] as $file) {
                $outsourceRequest->addMedia($file)->toMediaCollection(OutsourceRequest::REQUEST_FILES_COLLECTION);
            }

            return $outsourceRequest;
        });

        return OutsourceRequestResource::make($outsourceRequest->load(['offers.institution']));
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Put(
        path: '/outsource-requests/{id}',
        summary: 'Reorder PENDING cascade offers',
        requestBody: new OAH\RequestBody(OutsourceRequestReorderRequest::class),
        tags: ['Outsource requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: OutsourceRequestResource::class, description: 'Updated outsource request')]
    public function update(OutsourceRequestReorderRequest $request, string $id): OutsourceRequestResource
    {
        /** @var OutsourceRequest $outsourceRequest */
        $outsourceRequest = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('update', $outsourceRequest);

        $validated = $request->validated();

        DB::transaction(function () use ($outsourceRequest, $validated) {
            foreach ($validated['recipients'] as $item) {
                $outsourceRequest->offers()
                    ->where('id', $item['id'])
                    ->where('status', OutsourceOfferStatus::RequestPending)
                    ->update(['position' => $item['position']]);
            }
        });

        return OutsourceRequestResource::make(
            $outsourceRequest->fresh()->load(['offers.institution'])
        );
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/outsource-requests/{id}/cancel',
        summary: 'Cancel an outsource request',
        requestBody: new OAH\RequestBody(OutsourceRequestCancelRequest::class),
        tags: ['Outsource requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: OutsourceRequestResource::class, description: 'Cancelled outsource request')]
    public function cancel(OutsourceRequestCancelRequest $request, string $id): OutsourceRequestResource
    {
        /** @var OutsourceRequest $outsourceRequest */
        $outsourceRequest = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('cancel', $outsourceRequest);

        try {
            $this->stateMachine->cancelRequest($outsourceRequest, $request->validated('cancellation_reason'));
        } catch (DomainException $exception) {
            abort(Response::HTTP_CONFLICT, $exception->getMessage());
        }

        return OutsourceRequestResource::make(
            $outsourceRequest->fresh()->load(['offers.institution'])
        );
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Post(
        path: '/outsource-requests/{id}/select',
        summary: 'Select an offer as the executor',
        requestBody: new OAH\RequestBody(OutsourceRequestSelectRequest::class),
        tags: ['Outsource requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: OutsourceRequestResource::class, description: 'Fulfilled outsource request')]
    public function select(OutsourceRequestSelectRequest $request, string $id): OutsourceRequestResource
    {
        /** @var OutsourceRequest $outsourceRequest */
        $outsourceRequest = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('select', $outsourceRequest);

        if ($outsourceRequest->status !== OutsourceRequestStatus::Active) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Request is not ACTIVE.');
        }

        /** @var OutsourceOffer $offer */
        $offer = $outsourceRequest->offers()->findOrFail($request->validated('offer_id'));

        if ($offer->status !== OutsourceOfferStatus::RequestAccepted) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Offer is not in REQUEST_ACCEPTED state.');
        }

        $rejectionComments = collect($request->validated('rejection_comments', []))
            ->pluck('rejection_comment', 'offer_id')
            ->all();

        try {
            $this->stateMachine->selectOffer($outsourceRequest, $offer, $rejectionComments);
        } catch (DomainException $exception) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage());
        }

        return OutsourceRequestResource::make(
            $outsourceRequest->fresh()->load(['offers.institution'])
        );
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/outsource-requests/{id}/accept',
        summary: 'Accept an outsource request',
        requestBody: new OAH\RequestBody(OutsourceRequestAcceptRequest::class),
        tags: ['Outsource requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: OutsourceRequestResource::class, description: 'Accepted outsource request')]
    public function accept(OutsourceRequestAcceptRequest $request, string $id): OutsourceRequestResource
    {
        /** @var OutsourceRequest $outsourceRequest */
        $outsourceRequest = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('accept', $outsourceRequest);

        /** @var OutsourceOffer $offer */
        $offer = $outsourceRequest->offers
            ->firstWhere('institution_id', Auth::user()->institutionId);

        $validated = $request->validated();
        try {
            $this->stateMachine->acceptOffer(
                $offer,
                isset($validated['proposed_price']) ? (float)$validated['proposed_price'] : null,
                $validated['response_comment'] ?? null,
            );
        } catch (DomainException $exception) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage());
        }

        return OutsourceRequestResource::make(
            $outsourceRequest->fresh()->load(['offers.institution'])
        );
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/outsource-requests/{id}/decline',
        summary: 'Decline an outsource request',
        requestBody: new OAH\RequestBody(OutsourceRequestDeclineRequest::class),
        tags: ['Outsource requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: OutsourceRequestResource::class, description: 'Declined outsource request')]
    public function decline(OutsourceRequestDeclineRequest $request, string $id): OutsourceRequestResource
    {
        /** @var OutsourceRequest $outsourceRequest */
        $outsourceRequest = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('decline', $outsourceRequest);

        /** @var OutsourceOffer $offer */
        $offer = $outsourceRequest->offers
            ->firstWhere('institution_id', Auth::user()->institutionId);

        try {
            $this->stateMachine->declineOffer($offer, $request->validated('decline_comment'));
        } catch (DomainException $exception) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage());
        }

        return OutsourceRequestResource::make(
            $outsourceRequest->fresh()->load(['offers.institution'])
        );
    }

    private function getBaseQuery(): Builder
    {
        return OutsourceRequest::query()
            ->withGlobalScope('policy', OutsourceRequestPolicy::scope());
    }
}
