<?php

namespace App\Http\Requests\API;

use App\Enums\OutsourceRequestPriceMode;
use App\Models\InstitutionPartner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['assignment_id', 'price_mode', 'offers'],
        properties: [
            new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'price_mode', type: 'string', enum: OutsourceRequestPriceMode::class),
            new OA\Property(
                property: 'offers',
                type: 'array',
                items: new OA\Items(
                    required: ['institution_id'],
                    properties: [new OA\Property(property: 'institution_id', type: 'string', format: 'uuid')],
                    type: 'object',
                )
            ),
            new OA\Property(property: 'price', description: 'Required when price_mode is FIXED_PRICE; must be omitted otherwise.', type: 'number', format: 'double', nullable: true),
        ]
    )
)]
class OutsourceRequestPreviewRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'assignment_id' => 'required|uuid|exists:assignments,id',
            'price_mode' => 'required|string|in:PRICELIST_BASED,FIXED_PRICE,ASK_FOR_PRICE',
            'offers' => 'required|array|min:1',
            'offers.*.institution_id' => 'required|uuid',
            'price' => 'nullable|numeric|gt:0',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $callerInstitutionId = Auth::user()->institutionId;
                $recipientIds = collect($this->input('offers', []))->pluck('institution_id')->filter();

                $validPartnerIds = InstitutionPartner::query()
                    ->where('institution_id', $callerInstitutionId)
                    ->whereIn('partner_institution_id', $recipientIds)
                    ->whereHas('partnerInstitution', fn ($q) => $q->whereNull('deleted_at'))
                    ->pluck('partner_institution_id')
                    ->all();

                foreach ($recipientIds as $index => $institutionId) {
                    if (!in_array($institutionId, $validPartnerIds, true)) {
                        $validator->errors()->add(
                            "offers.{$index}.institution_id",
                            'Institution is not an active partner of your institution.'
                        );
                    }
                }
            },
            function (Validator $validator) {
                $priceMode = $this->input('price_mode');
                $price = $this->input('price');

                if ($priceMode === OutsourceRequestPriceMode::FixedPrice->value) {
                    if ($price === null) {
                        $validator->errors()->add('price', 'Price is required.');
                    }
                } elseif (in_array($priceMode, [OutsourceRequestPriceMode::PriceListBased->value, OutsourceRequestPriceMode::AskForPrice->value], true)) {
                    if ($price !== null) {
                        $validator->errors()->add('price', 'Price must be omitted.');
                    }
                }
            },
        ];
    }
}
