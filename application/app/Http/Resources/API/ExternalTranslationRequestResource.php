<?php

namespace App\Http\Resources\API;

use App\Enums\ExternalRequestRecipientStatus;
use App\Http\Resources\MediaResource;
use App\Models\AuthUser;
use App\Models\ExternalTranslationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use OpenApi\Attributes as OA;

/**
 * @mixin ExternalTranslationRequest
 */
#[OA\Schema(
    title: 'ExternalTranslationRequest',
    required: ['id', 'assignment_id', 'mode', 'status', 'include_price', 'include_source_files', 'is_cascade_exhausted'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'mode', type: 'string'),
        new OA\Property(property: 'reaction_time_minutes', type: 'integer', nullable: true),
        new OA\Property(property: 'deadline_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'special_instructions', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'include_price', type: 'boolean'),
        new OA\Property(property: 'include_source_files', type: 'boolean'),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'is_cascade_exhausted', type: 'boolean'),
        new OA\Property(property: 'recipients', type: 'array', items: new OA\Items(ref: ExternalTranslationRequestRecipientResource::class), nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class ExternalTranslationRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'mode' => $this->mode,
            'reaction_time_minutes' => $this->reaction_time_minutes,
            'deadline_at' => $this->deadline_at,
            'special_instructions' => $this->special_instructions,
            'price' => $this->price,
            'include_price' => $this->include_price,
            'include_source_files' => $this->include_source_files,
            'status' => $this->status,
            'is_cascade_exhausted' => $this->computeIsCascadeExhausted(),
            'recipients' => $this->whenLoaded('recipients', fn() => ExternalTranslationRequestRecipientResource::collection(
                $this->visibleRecipients($request)
            )),
            'media' => $this->whenLoaded('media', fn() => MediaResource::collection($this->getMedia(ExternalTranslationRequest::REQUEST_FILES_COLLECTION))),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Owner institution sees all recipients; everyone else (i.e. partner/recipient
     * institutions) sees only their own institution's row to avoid leaking
     * competitors' bids and comments.
     */
    private function visibleRecipients(Request $request): Collection
    {
        /** @var AuthUser|null $user */
        $user = $request->user();

        if ($this->ownerInstitution->id === $user?->institutionId) {
            return $this->recipients;
        }

        return $this->recipients
            ->where('institution_id', $user?->institutionId)
            ->values();
    }

    private function computeIsCascadeExhausted(): bool
    {
        if (!$this->isCascade()) {
            return false;
        }

        // If recipients are loaded, check in memory to avoid extra queries
        if ($this->relationLoaded('recipients')) {
            return $this->recipients
                ->whereIn('status', [ExternalRequestRecipientStatus::Pending, ExternalRequestRecipientStatus::Notified])
                ->isEmpty();
        }

        return !$this->recipients()
            ->whereIn('status', [ExternalRequestRecipientStatus::Pending, ExternalRequestRecipientStatus::Notified])
            ->exists();
    }
}
