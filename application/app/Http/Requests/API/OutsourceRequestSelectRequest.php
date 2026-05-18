<?php

namespace App\Http\Requests\API;

use App\Enums\OutsourceOfferStatus;
use App\Models\OutsourceRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['offer_id', 'rejection_comments'],
        properties: [
            new OA\Property(property: 'offer_id', type: 'string', format: 'uuid'),
            new OA\Property(
                property: 'rejection_comments',
                type: 'array',
                items: new OA\Items(
                    required: ['offer_id', 'rejection_comment'],
                    properties: [
                        new OA\Property(property: 'offer_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'rejection_comment', type: 'string'),
                    ],
                    type: 'object',
                ),
            ),
        ]
    )
)]
class OutsourceRequestSelectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'offer_id' => ['required', 'uuid'],
            'rejection_comments' => ['present', 'array'],
            'rejection_comments.*.offer_id' => ['required', 'uuid', 'distinct'],
            'rejection_comments.*.rejection_comment' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $request = OutsourceRequest::query()->find($this->route('id'));
                if ($request === null) {
                    return;
                }

                $selectedId = $this->input('offer_id');
                $submittedIds = collect($this->input('rejection_comments', []))->pluck('offer_id');

                if ($submittedIds->contains($selectedId)) {
                    $validator->errors()->add(
                        'rejection_comments',
                        'The selected offer must not appear in rejection_comments.',
                    );

                    return;
                }

                $expectedIds = $request->offers()
                    ->whereIn('status', [
                        OutsourceOfferStatus::RequestPending,
                        OutsourceOfferStatus::RequestSent,
                        OutsourceOfferStatus::RequestAccepted,
                    ])
                    ->where('id', '!=', $selectedId)
                    ->pluck('id');

                $missing = $expectedIds->diff($submittedIds);
                if ($missing->isNotEmpty()) {
                    $validator->errors()->add(
                        'rejection_comments',
                        'A rejection_comment is required for every non-selected in-play offer. Missing: ' . $missing->implode(', '),
                    );
                }

                $extra = $submittedIds->diff($expectedIds);
                if ($extra->isNotEmpty()) {
                    $validator->errors()->add(
                        'rejection_comments',
                        'rejection_comments contains offer_id(s) not eligible for rejection (must be in PENDING/NOTIFIED/ACCEPTED on this request and not the selected one): ' . $extra->implode(', '),
                    );
                }
            },
        ];
    }
}
