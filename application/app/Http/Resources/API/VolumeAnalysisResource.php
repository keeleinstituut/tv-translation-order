<?php

namespace App\Http\Resources\API;

use App\Services\CatTools\CatAnalysisResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin CatAnalysisResult
 */
#[OA\Schema(
    required: [
        'total',
        'tm_101',
        'repetitions',
        'tm_100',
        'tm_95_99',
        'tm_85_94',
        'tm_75_84',
        'tm_50_74',
        'tm_0_49',
    ],
    properties: [
        new OA\Property(property: 'total', type: 'integer'),
        new OA\Property(property: 'tm_101', type: 'integer'),
        new OA\Property(property: 'repetitions', type: 'integer'),
        new OA\Property(property: 'tm_100', type: 'integer'),
        new OA\Property(property: 'tm_95_99', type: 'integer'),
        new OA\Property(property: 'tm_85_94', type: 'integer'),
        new OA\Property(property: 'tm_75_84', type: 'integer'),
        new OA\Property(property: 'tm_50_74', type: 'integer'),
        new OA\Property(property: 'tm_0_49', type: 'integer'),
    ],
    type: 'object'
)]
class VolumeAnalysisResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->total,
            'tm_101' => $this->tm_101,
            'repetitions' => $this->repetitions,
            'tm_100' => $this->tm_100,
            'tm_95_99' => $this->tm_95_99,
            'tm_85_94' => $this->tm_85_94,
            'tm_75_84' => $this->tm_75_84,
            'tm_50_74' => $this->tm_50_74,
            'tm_0_49' => $this->tm_0_49,
        ];
    }
}
