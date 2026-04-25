<?php

namespace App\Http\Controllers\API;

use App\Enums\ExternalRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\ExternalTranslationRequestRecipientAcceptRequest;
use App\Http\Requests\API\ExternalTranslationRequestRecipientDeclineRequest;
use App\Http\Requests\API\ExternalTranslationRequestRecipientListRequest;
use App\Http\Resources\API\ExternalTranslationRequestInboxDetailsResource;
use App\Http\Resources\API\ExternalTranslationRequestInboxResource;
use App\Models\ExternalTranslationRequestRecipient;
use App\Policies\ExternalTranslationRequestRecipientPolicy;
use App\Services\ExternalTranslationRequest\ExternalTranslationRequestStateMachine;
use AuditLogClient\Services\AuditLogPublisher;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class ExternalTranslationRequestRecipientController extends Controller
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
        path: '/external-translation-request-recipients',
        summary: 'List external translation request recipients (partner inbox)',
        tags: ['External translation requests'],
        parameters: [
            new OA\QueryParameter(name: 'status[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string'), nullable: true)),
            new OA\QueryParameter(name: 'search', schema: new OA\Schema(type: 'string', nullable: true)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['created_at', 'notified_at'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc'])),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: ExternalTranslationRequestInboxResource::class)]
    public function index(ExternalTranslationRequestRecipientListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ExternalTranslationRequestRecipient::class);

        $params = collect($request->validated());

        $query = $this->getBaseQuery()
            ->with([
                'externalTranslationRequest.assignment.subProject.project.institution',
                'externalTranslationRequest.assignment.subProject.sourceLanguageClassifierValue',
                'externalTranslationRequest.assignment.subProject.destinationLanguageClassifierValue',
                'externalTranslationRequest.createdByInstitutionUser',
            ]);

        if ($statuses = $params->get('status')) {
            $query->whereIn('status', $statuses);
        }

        $query->whereHas('externalTranslationRequest', fn(Builder $q) => $q->where('status', ExternalRequestStatus::Active));

        if ($search = $params->get('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('externalTranslationRequest.assignment.subProject.project',
                    fn(Builder $p) => $p->where('ext_id', 'ILIKE', "%{$search}%")
                );
            });
        }

        $sortBy = $params->get('sort_by', 'created_at');
        $sortOrder = $params->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        return ExternalTranslationRequestInboxResource::collection(
            $query->paginate($params->get('per_page', 10))
        );
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/external-translation-request-recipients/{id}',
        summary: 'Show an external translation request recipient (partner inbox detail)',
        tags: ['External translation requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ExternalTranslationRequestInboxDetailsResource::class, description: 'External translation request recipient detail')]
    public function show(string $id): ExternalTranslationRequestInboxDetailsResource
    {
        $this->authorize('viewAny', ExternalTranslationRequestRecipient::class);

        $recipient = $this->getDetailQuery()->findOrFail($id);

        $this->authorize('view', $recipient);

        return ExternalTranslationRequestInboxDetailsResource::make($recipient);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/external-translation-request-recipients/{id}/accept',
        summary: 'Accept an external translation request',
        requestBody: new OAH\RequestBody(ExternalTranslationRequestRecipientAcceptRequest::class),
        tags: ['External translation requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ExternalTranslationRequestInboxDetailsResource::class, description: 'Accepted recipient')]
    public function accept(ExternalTranslationRequestRecipientAcceptRequest $request, string $id): ExternalTranslationRequestInboxDetailsResource
    {
        /** @var ExternalTranslationRequestRecipient $recipient */
        $recipient = $this->getDetailQuery()->findOrFail($id);
        $this->authorize('accept', $recipient);

        $validated = $request->validated();
        try {
            $this->stateMachine->acceptRecipient(
                $recipient,
                isset($validated['proposed_price']) ? (float)$validated['proposed_price'] : null,
                $validated['response_comment'] ?? null,
            );
        } catch (DomainException $exception) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage());
        }

        return ExternalTranslationRequestInboxDetailsResource::make(
            $recipient->fresh()->load($this->detailRelations())
        );
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/external-translation-request-recipients/{id}/decline',
        summary: 'Decline an external translation request',
        requestBody: new OAH\RequestBody(ExternalTranslationRequestRecipientDeclineRequest::class),
        tags: ['External translation requests'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ExternalTranslationRequestInboxDetailsResource::class, description: 'Declined recipient')]
    public function decline(ExternalTranslationRequestRecipientDeclineRequest $request, string $id): ExternalTranslationRequestInboxDetailsResource
    {
        /** @var ExternalTranslationRequestRecipient $recipient */
        $recipient = $this->getDetailQuery()->findOrFail($id);
        $this->authorize('decline', $recipient);

        try {
            $this->stateMachine->declineRecipient($recipient, $request->validated('decline_comment'));
        } catch (DomainException $exception) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage());
        }

        return ExternalTranslationRequestInboxDetailsResource::make(
            $recipient->fresh()->load($this->detailRelations())
        );
    }

    private function getBaseQuery(): Builder
    {
        return ExternalTranslationRequestRecipient::query()
            ->withGlobalScope('policy', ExternalTranslationRequestRecipientPolicy::scope());
    }

    private function getDetailQuery(): Builder
    {
        return $this->getBaseQuery()->with($this->detailRelations());
    }

    private function detailRelations(): array
    {
        return [
            'externalTranslationRequest.assignment.subProject.project.institution',
            'externalTranslationRequest.assignment.subProject.project.typeClassifierValue',
            'externalTranslationRequest.assignment.subProject.project.translationDomainClassifierValue',
            'externalTranslationRequest.assignment.subProject.sourceLanguageClassifierValue',
            'externalTranslationRequest.assignment.subProject.destinationLanguageClassifierValue',
            'externalTranslationRequest.createdByInstitutionUser',
            'externalTranslationRequest.media',
        ];
    }
}
