<?php

namespace App\Http\Resources\API;

use App\Enums\ExternalRequestMode;
use App\Enums\ExternalRequestRecipientStatus;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use OpenApi\Attributes as OA;

/**
 * Partner inbox list row.
 *
 * @mixin ExternalTranslationRequestRecipient
 */
#[OA\Schema(
    title: 'ExternalTranslationRequestInbox',
    required: ['id', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'response_deadline', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'external_translation_request_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'mode', type: 'string'),
        new OA\Property(property: 'project_ext_id', type: 'string', nullable: true),
        new OA\Property(property: 'requestor_institution_name', type: 'string', nullable: true),
        new OA\Property(property: 'requestor_email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'source_language', type: 'object', nullable: true),
        new OA\Property(property: 'destination_language', type: 'object', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class ExternalTranslationRequestInboxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translationRequest = $this->externalTranslationRequest;
        $assignment = $translationRequest->assignment;
        $subProject = $assignment->subProject;
        $project = $subProject->project;
        $creator = $translationRequest->createdByInstitutionUser;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'response_deadline' => $this->resolveResponseDeadline($translationRequest),
            'external_translation_request_id' => $translationRequest->id,
            'mode' => $translationRequest->mode,
            'project_ext_id' => $project->ext_id ?? null,
            'requestor_institution_name' => $project->institution?->name,
            'requestor_email' => $creator?->email,
            'source_language' => ClassifierValueResource::make($subProject->sourceLanguageClassifierValue),
            'destination_language' => ClassifierValueResource::make($subProject->destinationLanguageClassifierValue),
            'created_at' => $this->created_at,
        ];
    }

    private function resolveResponseDeadline(ExternalTranslationRequest $translationRequest): ?Carbon
    {
        if ($this->status === ExternalRequestRecipientStatus::Notified
            && $translationRequest->mode === ExternalRequestMode::Cascade
        ) {
            return $this->expires_at;
        }

        if ($translationRequest->mode === ExternalRequestMode::Parallel) {
            return $translationRequest->deadline_at;
        }

        return null;
    }
}
