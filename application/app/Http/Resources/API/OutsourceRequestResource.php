<?php

namespace App\Http\Resources\API;

use App\Enums\ExternalRequestMode;
use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Http\Resources\MediaResource;
use App\Models\AuthUser;
use App\Models\OutsourceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use OpenApi\Attributes as OA;

/**
 * @mixin OutsourceRequest
 */
#[OA\Schema(
    title: 'OutsourceRequest',
    required: ['id', 'assignment_id', 'mode', 'reaction_time_minutes', 'status', 'include_price', 'include_source_files', 'is_cascade_exhausted'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'mode', type: 'string', enum: ExternalRequestMode::class),
        new OA\Property(property: 'reaction_time_minutes', type: 'integer'),
        new OA\Property(property: 'deadline_at', description: 'Computed for PARALLEL mode (created_at + reaction_time_minutes); null for CASCADE.', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'special_instructions', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'include_price', type: 'boolean'),
        new OA\Property(property: 'include_source_files', type: 'boolean'),
        new OA\Property(property: 'status', type: 'string', enum: OutsourceRequestStatus::class),
        new OA\Property(property: 'is_cascade_exhausted', type: 'boolean'),
        new OA\Property(property: 'assignment', ref: AssignmentResource::class, nullable: true),
        new OA\Property(property: 'offers', type: 'array', items: new OA\Items(ref: OutsourceOfferResource::class), nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class OutsourceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'mode' => $this->mode,
            'reaction_time_minutes' => $this->reaction_time_minutes,
            'deadline_at' => $this->effectiveDeadlineAt(),
            'special_instructions' => $this->special_instructions,
            'price' => $this->price,
            'include_price' => $this->include_price,
            'include_source_files' => $this->include_source_files,
            'status' => $this->status,
            'assignment' => AssignmentResource::make($this->whenLoaded('assignment')),
            'offers' => $this->whenLoaded('offers', fn() => OutsourceOfferResource::collection(
                $this->visibleOffers($request)
            )),
            'media' => $this->whenLoaded('media', fn() => MediaResource::collection($this->getMedia(OutsourceRequest::REQUEST_FILES_COLLECTION))),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Owner institution sees all offers; everyone else (i.e. partner/offer
     * institutions) sees only their own institution's row to avoid leaking
     * competitors' bids and comments.
     */
    private function visibleOffers(Request $request): Collection
    {
        /** @var AuthUser|null $user */
        $user = $request->user();

        if ($this->ownerInstitution->id === $user?->institutionId) {
            return $this->offers;
        }

        return $this->offers
            ->where('institution_id', $user?->institutionId)
            ->values();
    }
}
