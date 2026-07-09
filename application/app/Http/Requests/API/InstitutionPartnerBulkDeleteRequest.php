<?php

namespace App\Http\Requests\API;

use App\Models\InstitutionPartner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['id'],
        properties: [
            new OA\Property(
                property: 'id',
                type: 'array',
                items: new OA\Items(type: 'string', format: 'uuid'),
                minItems: 1
            ),
        ]
    )
)]
class InstitutionPartnerBulkDeleteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required|array|min:1',
            'id.*' => [
                'uuid',
                'distinct',
                Rule::exists(InstitutionPartner::class, 'id'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['id', 'id.*'])) {
                    return;
                }

                $ids = collect($this->input('id', []));
                if ($ids->isEmpty()) {
                    return;
                }

                $allowedIds = InstitutionPartner::query()
                    ->whereIn('id', $ids)
                    ->where('institution_id', Auth::user()->institutionId)
                    ->pluck('id');

                $ids->diff($allowedIds)->each(function (string $invalidId, int $index) use ($validator): void {
                    $validator->errors()->add("id.$index", 'The selected id is invalid.');
                });
            },
        ];
    }
}
