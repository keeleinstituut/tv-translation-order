<?php

namespace App\Http\Requests\API;

use App\Models\InstitutionMainLanguage;
use App\Policies\InstitutionMainLanguagePolicy;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['institution_main_language_id'],
        properties: [
            new OA\Property(
                property: 'institution_main_language_id',
                description: 'InstitutionMainLanguage ID from the current institution to pin/unpin',
                type: 'string',
                format: 'uuid',
            ),
        ]
    )
)]
class PinLanguageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'institution_main_language_id' => [
                'required',
                'uuid',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $exists = InstitutionMainLanguage::withGlobalScope('policy', InstitutionMainLanguagePolicy::scope())
                        ->where('id', $value)
                        ->exists();

                    if (! $exists) {
                        $fail('validation.exists')->translate();
                    }
                },
            ],
        ];
    }
}
