<?php

namespace App\Http\Requests\API;

use App\Enums\OutsourceRequestPriceMode;
use App\Models\OutsourceOffer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: false,
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'price', description: 'Required for ASK_FOR_PRICE requests; must be omitted for FIXED_PRICE requests.', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'response_comment', type: 'string', nullable: true),
        ]
    )
)]
class OutsourceOfferAcceptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'price' => 'nullable|numeric|gt:0',
            'response_comment' => 'nullable|string',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $offer = OutsourceOffer::with('outsourceRequest')->find($this->route('id'));
                $outsourceRequest = $offer?->outsourceRequest;
                if (!$outsourceRequest) {
                    return;
                }

                $price = $this->input('price');

                if ($outsourceRequest->price_mode === OutsourceRequestPriceMode::AskForPrice && $price === null) {
                    $validator->errors()->add('price', 'Price is required.');
                }

                if ($outsourceRequest->price_mode === OutsourceRequestPriceMode::FixedPrice && $price !== null) {
                    $validator->errors()->add('price', 'Price is not allowed.');
                }
            },
        ];
    }
}
