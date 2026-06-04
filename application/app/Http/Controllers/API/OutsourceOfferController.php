<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\OutsourceOfferListRequest;
use App\Http\Requests\API\OutsourceOfferAcceptRequest;
use App\Http\Requests\API\OutsourceOfferDeclineRequest;
use App\Http\Resources\API\OutsourceOfferResource;
use App\Models\OutsourceOffer;
use App\Policies\OutsourceOfferPolicy;
use App\Services\OutsourceRequest\OutsourceRequestStateMachine;
use AuditLogClient\Services\AuditLogPublisher;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

class OutsourceOfferController extends Controller
{
    public function __construct(
        AuditLogPublisher $auditLogPublisher,
        private readonly OutsourceRequestStateMachine $stateMachine,
    ) {
        parent::__construct($auditLogPublisher);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/outsource-offers',
        summary: 'List outsource offers for the current institution',
        tags: ['Outsource offers'],
        parameters: [
            new OA\QueryParameter(name: 'assignment_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'sub_project_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'project_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'status[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', enum: \App\Enums\OutsourceOfferStatus::class), nullable: true)),
            new OA\QueryParameter(name: 'per_page', schema: new OA\Schema(type: 'number', default: 10, maximum: 50, nullable: true)),
            new OA\QueryParameter(name: 'sort_by', schema: new OA\Schema(type: 'string', default: 'created_at', enum: ['created_at', 'expires_at'])),
            new OA\QueryParameter(name: 'sort_order', schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc'])),
            new OA\QueryParameter(
                name: 'type_classifier_value_ids',
                description: 'Filter the result set to offers which have any of the specified project types.',
                schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', format: 'uuid'))
            ),
            new OA\QueryParameter(name: 'institution_id', schema: new OA\Schema(type: 'string', format: 'uuid', nullable: true)),
            new OA\QueryParameter(name: 'language_directions[]', schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string'), nullable: true)),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\CollectionResponse(itemsRef: OutsourceOfferResource::class)]
    public function index(OutsourceOfferListRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', OutsourceOffer::class);

        $params = collect($request->validated());
        $query = $this->getBaseQuery()->with([
            'outsourceRequest.ownerInstitution',
            'outsourceRequest.assignment.subProject.sourceLanguageClassifierValue',
            'outsourceRequest.assignment.subProject.destinationLanguageClassifierValue',
            'outsourceRequest.assignment.jobDefinition',
        ]);

        if ($param = $params->get('assignment_id')) {
            $query->whereHas('outsourceRequest.assignment', fn(Builder $q) => $q->where('id', $param));
        }

        if ($param = $params->get('sub_project_id')) {
            $query->whereHas('outsourceRequest.assignment.subProject', fn(Builder $q) => $q->where('id', $param));
        }

        if ($param = $params->get('project_id')) {
            $query->whereHas('outsourceRequest.assignment.subProject.project', fn(Builder $q) => $q->where('id', $param));
        }

        if ($param = $params->get('q')) {
            $query->where(function (Builder $q) use ($param) {
                $q->whereHas('outsourceRequest.assignment', fn(Builder $q) => $q->where('ext_id', 'ilike', "%$param%"))
                    ->orWhereHas('outsourceRequest.assignment.subProject.project', fn(Builder $q) =>
                        $q->where('ext_id', 'ilike', "%$param%")
                            ->orWhere('reference_number', 'ilike', "%$param%")
                    )
                    ->orWhereHas('institution', fn(Builder $q) => $q->where('email', 'ilike', "%$param%"));
            });
        }

        if ($param = $params->get('type_classifier_value_ids')) {
            $query->whereHas('outsourceRequest.assignment.subProject.project', fn(Builder $q) => $q->whereIn('type_classifier_value_id', $param));
        }

        if ($param = $params->get('status')) {
            $query->whereIn('status', $param);
        }

        if ($param = $params->get('institution_id')) {
            $query->where('institution_id', $param);
        }

        if ($params->get('language_directions')) {
            $query->where(function (Builder $q) use ($request) {
                collect($request->getLanguagesZippedByDirections())->eachSpread(
                    function (string $src, string $dst) use ($q) {
                        $q->orWhereHas('outsourceRequest.assignment.subProject', fn(Builder $sq) => $sq->where([
                            'source_language_classifier_value_id' => $src,
                            'destination_language_classifier_value_id' => $dst,
                        ]));
                    }
                );
            });
        }

        $sortBy = $params->get('sort_by', 'created_at');
        $sortOrder = $params->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        return OutsourceOfferResource::collection(
            $query->paginate($params->get('per_page', 10))
        );
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Get(
        path: '/outsource-offers/{id}',
        summary: 'Show an outsource offer',
        tags: ['Outsource offers'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: OutsourceOfferResource::class, description: 'Outsource offer')]
    public function show(string $id): OutsourceOfferResource
    {
        /** @var OutsourceOffer $offer */
        $offer = $this->getBaseQuery()->with($this->relationsToLoad())->findOrFail($id);
        $this->authorize('view', $offer);

        return OutsourceOfferResource::make($offer);
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/outsource-offers/{id}/accept',
        summary: 'Accept an outsource offer',
        requestBody: new OAH\RequestBody(OutsourceOfferAcceptRequest::class),
        tags: ['Outsource offers'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: OutsourceOfferResource::class, description: 'Accepted outsource offer')]
    public function accept(OutsourceOfferAcceptRequest $request, string $id): OutsourceOfferResource
    {
        /** @var OutsourceOffer $offer */
        $offer = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('accept', $offer);

        $validated = $request->validated();
        try {
            $this->stateMachine->acceptOffer(
                $offer,
                isset($validated['price']) ? (float)$validated['price'] : null,
                $validated['response_comment'] ?? null,
            );
        } catch (DomainException $exception) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage());
        }

        return OutsourceOfferResource::make($offer->fresh()->load($this->relationsToLoad()));
    }

    /**
     * @throws AuthorizationException
     */
    #[OA\Post(
        path: '/outsource-offers/{id}/decline',
        summary: 'Decline an outsource offer',
        requestBody: new OAH\RequestBody(OutsourceOfferDeclineRequest::class),
        tags: ['Outsource offers'],
        parameters: [
            new OA\PathParameter(name: 'id', schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: OutsourceOfferResource::class, description: 'Declined outsource offer')]
    public function decline(OutsourceOfferDeclineRequest $request, string $id): OutsourceOfferResource
    {
        /** @var OutsourceOffer $offer */
        $offer = $this->getBaseQuery()->findOrFail($id);
        $this->authorize('decline', $offer);

        try {
            $this->stateMachine->declineOffer($offer, $request->validated('decline_comment'));
        } catch (DomainException $exception) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, $exception->getMessage());
        }

        return OutsourceOfferResource::make($offer->fresh()->load($this->relationsToLoad()));
    }

    private function getBaseQuery(): Builder
    {
        return OutsourceOffer::query()
            ->withGlobalScope('policy', OutsourceOfferPolicy::scope());
    }

    /**
     * OutsourceOffer relations to eager load for show / accept / decline responses.
     * @return string[]
     */
    private function relationsToLoad(): array
    {
        return [
            'institution',
            'outsourceRequest.media',
            'outsourceRequest.ownerInstitution',
            'outsourceRequest.assignment.subProject.sourceLanguageClassifierValue',
            'outsourceRequest.assignment.subProject.destinationLanguageClassifierValue',
            'outsourceRequest.assignment.subProject.project.sourceFiles',
            'outsourceRequest.assignment.subProject.project.managerInstitutionUser',
            'outsourceRequest.assignment.volumes',
            'outsourceRequest.assignment.jobDefinition',
        ];
    }
}
