<?php

namespace App\Http\Resources\API;

use App\Http\Resources\MediaResource;
use App\Models\ProjectReviewRejection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin ProjectReviewRejection
 */
#[OA\Schema(
    required: [
        'id',
        'created_at',
        'files'
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'files', type: 'array', items: new OA\Items(ref: MediaResource::class)),
    ],
    type: 'object'
)]
class ProjectReviewRejectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(
                'id',
                'created_at',
            ),
            'files' => MediaResource::collection($this->whenLoaded('files')),
        ];
    }
}
