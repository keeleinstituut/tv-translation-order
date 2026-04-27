<?php

namespace App\Http\Controllers\API;

use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\ExternalTranslationRequestCreateRequest;
use App\Http\Requests\API\ExternalTranslationRequestListRequest;
use App\Http\Requests\API\ExternalTranslationRequestReorderRequest;
use App\Http\Requests\API\ExternalTranslationRequestSelectRequest;
use App\Http\Resources\API\ExternalTranslationRequestResource;
use App\Models\Assignment;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use App\Models\InstitutionPartner;
use App\Policies\ExternalTranslationRequestPolicy;
use App\Services\ExternalTranslationRequest\ExternalTranslationRequestStateMachine;
use App\Jobs\ExpireExternalTranslationRequestRecipientJob;
use App\Services\Prices\ExternalPartnerAssignmentPriceCalculator;
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

class ExternalTranslationRequestController extends Controller
{
    public function __construct(
        AuditLogPublisher                                       $auditLogPublisher,
        private readonly ExternalTranslationRequestStateMachine $stateMachine,
    )
    {
        parent::__construct($auditLogPublisher);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/external-translation-requests',
        summary: 'List external translation requests for the current institution',
        tags: ['External translation requests'],
        parameters: [
            new OA\QueryParameter(name: 'assignment_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'sub_project_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'project_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'status[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string'), nullable: true)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['created_at'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc'])),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: ExternalTranslationRequestResource::class)]
    public function index(ExternalTranslationRequestListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ExternalTranslationRequest::class);

        $params = collect($request->validated());
        $query = $this->getBaseQuery();

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

        return ExternalTranslationRequestResource::collection(
            $query->paginate($params->get('per_page', 10))
        );
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/external-translation-requests/{id}',
        summary: 'Show an external translation request',
        tags: ['External translation requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ExternalTranslationRequestResource::class, description: 'External translation request')]
    public function show(string $id): ExternalTranslationRequestResource
    {
        $this->authorize('viewAny', ExternalTranslationRequest::class);

        $translationRequest = $this->getBaseQuery()
            ->with(['media'])
            ->findOrFail($id);

        $this->authorize('view', $translationRequest);

        return ExternalTranslationRequestResource::make($translationRequest);
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Post(
        path: '/external-translation-requests',
        summary: 'Create an external translation request',
        requestBody: new OAH\RequestBody(ExternalTranslationRequestCreateRequest::class),
        tags: ['External translation requests'],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ExternalTranslationRequestResource::class, description: 'Created external translation request', response: Response::HTTP_CREATED)]
    public function store(ExternalTranslationRequestCreateRequest $request): ExternalTranslationRequestResource
    {
        $validated = $request->validated();
        $assignment = Assignment::findOrFail($validated['assignment_id']);

        $this->authorize('create', [ExternalTranslationRequest::class, $assignment]);

        $translationRequest = DB::transaction(function () use ($validated, $assignment) {
            $translationRequest = ExternalTranslationRequest::create([
                'assignment_id' => $assignment->id,
                'created_by_institution_user_id' => Auth::user()->institutionUserId,
                'mode' => $validated['mode'],
                'reaction_time_minutes' => $validated['reaction_time_minutes'] ?? null,
                'deadline_at' => $validated['deadline_at'] ?? null,
                'special_instructions' => $validated['special_instructions'] ?? null,
                'price' => $validated['override_price'] ?? null,
                'include_price' => $validated['include_price'] ?? true,
                'include_source_files' => $validated['include_source_files'] ?? true,
                'status' => ExternalRequestStatus::Active,
            ]);

            $recipientInstitutionIds = collect($validated['recipients'])->pluck('institution_id');
            $partnersByInstitutionId = InstitutionPartner::query()
                ->where('institution_id', Auth::user()->institutionId)
                ->whereIn('partner_institution_id', $recipientInstitutionIds)
                ->get()
                ->keyBy('partner_institution_id');

            $isCascade = $translationRequest->isCascade();

            foreach ($validated['recipients'] as $index => $row) {
                /** @var InstitutionPartner $partner */
                $partner = $partnersByInstitutionId->get($row['institution_id']);
                $calculatedPrice = new ExternalPartnerAssignmentPriceCalculator($assignment, $partner)->getPrice();

                $notified = !$isCascade || $index === 0;

                $recipient = $translationRequest->recipients()->create([
                    'institution_id' => $row['institution_id'],
                    'status' => $notified
                        ? ExternalRequestRecipientStatus::Notified
                        : ExternalRequestRecipientStatus::Pending,
                    'notified_at' => $notified ? now() : null,
                    'expires_at' => match (true) {
                        $isCascade && $notified => now()->addMinutes($translationRequest->reaction_time_minutes),
                        !$isCascade => $translationRequest->deadline_at,
                        default => null,
                    },
                    'calculated_price' => $calculatedPrice,
                ]);

                if ($notified) {
                    ExpireExternalTranslationRequestRecipientJob::dispatch($recipient->id)
                        ->afterCommit()
                        ->delay($recipient->expires_at);
                }
            }

            foreach ($validated['request_files'] ?? [] as $file) {
                $translationRequest->addMedia($file)->toMediaCollection(ExternalTranslationRequest::REQUEST_FILES_COLLECTION);
            }

            return $translationRequest;
        });

        return ExternalTranslationRequestResource::make($translationRequest->load(['recipients.institution']));
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Put(
        path: '/external-translation-requests/{id}',
        summary: 'Reorder PENDING cascade recipients',
        requestBody: new OAH\RequestBody(ExternalTranslationRequestReorderRequest::class),
        tags: ['External translation requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ExternalTranslationRequestResource::class, description: 'Updated external translation request')]
    public function update(ExternalTranslationRequestReorderRequest $request, string $id): ExternalTranslationRequestResource
    {
        /** @var ExternalTranslationRequest $translationRequest */
        $translationRequest = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('update', $translationRequest);

        $validated = $request->validated();

        DB::transaction(function () use ($translationRequest, $validated) {
            foreach ($validated['recipients'] as $item) {
                $translationRequest->recipients()
                    ->where('id', $item['id'])
                    ->where('status', ExternalRequestRecipientStatus::Pending)
                    ->update(['position' => $item['position']]);
            }
        });

        return ExternalTranslationRequestResource::make(
            $translationRequest->fresh()->load(['recipients.institution'])
        );
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/external-translation-requests/{id}/cancel',
        summary: 'Cancel an external translation request',
        tags: ['External translation requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ExternalTranslationRequestResource::class, description: 'Cancelled external translation request')]
    public function cancel(string $id): ExternalTranslationRequestResource
    {
        /** @var ExternalTranslationRequest $translationRequest */
        $translationRequest = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('cancel', $translationRequest);

        if ($translationRequest->status !== ExternalRequestStatus::Active) {
            abort(Response::HTTP_CONFLICT, 'Request is not ACTIVE.');
        }

        try {
            $this->stateMachine->cancelRequest($translationRequest);
        } catch (DomainException $exception) {
            abort(Response::HTTP_CONFLICT, $exception->getMessage());
        }

        return ExternalTranslationRequestResource::make(
            $translationRequest->fresh()->load(['recipients.institution'])
        );
    }

    /**
     * @throws AuthorizationException
     * @throws Throwable
     */
    #[OA\Post(
        path: '/external-translation-requests/{id}/select',
        summary: 'Select a recipient as the executor',
        requestBody: new OAH\RequestBody(ExternalTranslationRequestSelectRequest::class),
        tags: ['External translation requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ExternalTranslationRequestResource::class, description: 'Fulfilled external translation request')]
    public function select(ExternalTranslationRequestSelectRequest $request, string $id): ExternalTranslationRequestResource
    {
        /** @var ExternalTranslationRequest $translationRequest */
        $translationRequest = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('select', $translationRequest);

        if ($translationRequest->status !== ExternalRequestStatus::Active) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Request is not ACTIVE.');
        }

        /** @var ExternalTranslationRequestRecipient $recipient */
        $recipient = $translationRequest->recipients()->findOrFail($request->validated('recipient_id'));

        if ($recipient->status !== ExternalRequestRecipientStatus::Accepted) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Recipient is not in ACCEPTED state.');
        }

        $rejectionComments = collect($request->validated('rejection_comments', []))
            ->pluck('rejection_comment', 'recipient_id')
            ->all();

        try {
            $this->stateMachine->selectRecipient($translationRequest, $recipient, $rejectionComments);
        } catch (DomainException $exception) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage());
        }

        return ExternalTranslationRequestResource::make(
            $translationRequest->fresh()->load(['recipients.institution'])
        );
    }

    private function getBaseQuery(): Builder
    {
        return ExternalTranslationRequest::query()
            ->withGlobalScope('policy', ExternalTranslationRequestPolicy::scope())
            ->with(['recipients.institution', 'assignment.subProject.project']);
    }
}
