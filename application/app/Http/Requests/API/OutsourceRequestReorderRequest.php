<?php

namespace App\Http\Requests\API;

use App\Enums\OutsourceOfferStatus;
use App\Models\OutsourceOffer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['recipients'],
        properties: [
            new OA\Property(
                property: 'recipients',
                type: 'array',
                items: new OA\Items(
                    required: ['id', 'position'],
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'position', type: 'integer', minimum: 1),
                    ],
                    type: 'object',
                )
            ),
        ]
    )
)]
class OutsourceRequestReorderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'recipients' => 'required|array|min:1',
            'recipients.*.id' => 'required|uuid',
            'recipients.*.position' => 'required|integer|min:1',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $requestId = $this->route('id');

                $pendingIds = OutsourceOffer::query()
                    ->where('outsource_request_id', $requestId)
                    ->where('status', OutsourceOfferStatus::RequestPending)
                    ->pluck('id')
                    ->sort()
                    ->values();

                $payloadIds = collect($this->input('recipients', []))
                    ->pluck('id')
                    ->sort()
                    ->values();

                if ($pendingIds->toJson() !== $payloadIds->toJson()) {
                    $validator->errors()->add(
                        'recipients',
                        'Payload must be an exact permutation of all PENDING recipient IDs.'
                    );
                }

                $positions = collect($this->input('recipients', []))->pluck('position');
                if ($positions->unique()->count() !== $positions->count()) {
                    $validator->errors()->add('recipients', 'Recipient positions must be unique.');
                }
            },
        ];
    }
}
