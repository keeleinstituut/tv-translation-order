<?php

namespace App\Http\Resources\API;

use App\Enums\OutsourceOfferStatus;
use App\Models\OutsourceOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin OutsourceOffer
 */
#[OA\Schema(
    title: 'OutsourceOffer',
    required: ['id', 'institution_id', 'position', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institution_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'position', type: 'integer'),
        new OA\Property(property: 'status', type: 'string', enum: OutsourceOfferStatus::class),
        new OA\Property(property: 'notified_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'responded_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'price', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'decline_comment', type: 'string', nullable: true),
        new OA\Property(property: 'rejection_comment', type: 'string', nullable: true),
        new OA\Property(property: 'response_comment', type: 'string', nullable: true),
        new OA\Property(property: 'institution', ref: InstitutionResource::class, type: 'object', nullable: true),
        new OA\Property(property: 'outsource_request', ref: OutsourceRequestResource::class, type: 'object', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class OutsourceOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'position' => $this->position,
            'status' => $this->status,
            'notified_at' => $this->notified_at,
            'responded_at' => $this->responded_at,
            'expires_at' => $this->expires_at,
            'price' => $this->price,
            'decline_comment' => $this->decline_comment,
            'rejection_comment' => $this->rejection_comment,
            'response_comment' => $this->response_comment,
            'institution' => InstitutionResource::make($this->whenLoaded('institution')),
            'outsource_request' => OutsourceRequestResource::make($this->whenLoaded('outsourceRequest'))
                ->hideMedia(self::hidesRequestMedia($this->status)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private static function hidesRequestMedia(OutsourceOfferStatus $status): bool
    {
        return in_array($status, [
            OutsourceOfferStatus::RequestCancelled,
            OutsourceOfferStatus::OfferDeclined,
            OutsourceOfferStatus::RequestExpired,
        ], true);
    }
}
