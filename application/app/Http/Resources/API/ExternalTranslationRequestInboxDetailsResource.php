<?php

namespace App\Http\Resources\API;

use App\Enums\ExternalRequestMode;
use App\Enums\ExternalRequestRecipientStatus;
use App\Http\Resources\MediaResource;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Partner inbox detail — used for show, accept, and decline responses.
 *
 * @mixin ExternalTranslationRequestRecipient
 */
#[OA\Schema(
    title: 'ExternalTranslationRequestInboxDetails',
    required: ['id', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'response_deadline', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'calculated_price', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'proposed_price', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'decline_comment', type: 'string', nullable: true),
        new OA\Property(property: 'response_comment', type: 'string', nullable: true),
        new OA\Property(property: 'requestor', type: 'object', nullable: true),
        new OA\Property(property: 'order', type: 'object', nullable: true),
        new OA\Property(property: 'request_files', type: 'array', items: new OA\Items(type: 'object'), nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class ExternalTranslationRequestInboxDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translationRequest = $this->externalTranslationRequest;
        $assignment = $translationRequest->assignment;
        $subProject = $assignment->subProject;
        $project = $subProject->project;
        $creator = $translationRequest->createdByInstitutionUser;

        $result = [
            'id' => $this->id,
            'status' => $this->status,
            'response_deadline' => $this->resolveResponseDeadline($translationRequest),
            'decline_comment' => $this->decline_comment,
            'response_comment' => $this->response_comment,
            'requestor' => [
                'institution_name' => $project->institution?->name,
                'contact_email' => $creator?->email,
                'phone' => $creator?->phone,
                'special_instructions' => $translationRequest->special_instructions,
                'deadline' => $project->deadline_at ?? null,
            ],
            'order' => [
                'ext_id' => $project->ext_id ?? null,
                'type_classifier_value' => ClassifierValueResource::make($project->typeClassifierValue),
                'translation_domain_classifier_value' => ClassifierValueResource::make($project->translationDomainClassifierValue),
                'deadline_at' => $project->deadline_at ?? null,
                'source_language_classifier_value' => ClassifierValueResource::make($subProject->sourceLanguageClassifierValue),
                'destination_language_classifier_value' => ClassifierValueResource::make($subProject->destinationLanguageClassifierValue),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($translationRequest->include_price) {
            $result['calculated_price'] = $this->calculated_price;
            $result['proposed_price'] = $this->proposed_price;
            $result['effective_price'] = $translationRequest->price ?? $this->calculated_price;
        }

        if ($translationRequest->include_source_files) {
            $result['request_files'] = MediaResource::collection($translationRequest->getMedia(ExternalTranslationRequest::REQUEST_FILES_COLLECTION));
        }

        return $result;
    }

    private function resolveResponseDeadline(ExternalTranslationRequest $translationRequest): ?\Illuminate\Support\Carbon
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
