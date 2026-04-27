<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\ExternalTranslationRequestRecipientAcceptRequest;
use App\Http\Requests\API\ExternalTranslationRequestRecipientDeclineRequest;
use App\Http\Resources\API\ExternalTranslationRequestInboxDetailsResource;
use App\Models\ExternalTranslationRequestRecipient;
use App\Policies\ExternalTranslationRequestRecipientPolicy;
use App\Services\ExternalTranslationRequest\ExternalTranslationRequestStateMachine;
use AuditLogClient\Services\AuditLogPublisher;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
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

    private function getDetailQuery(): Builder
    {
        return ExternalTranslationRequestRecipient::query()
            ->withGlobalScope('policy', ExternalTranslationRequestRecipientPolicy::scope())
            ->with($this->detailRelations());
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
