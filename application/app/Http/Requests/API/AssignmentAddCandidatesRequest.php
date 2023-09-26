<?php

namespace App\Http\Requests\API;

use App\Models\Candidate;
use App\Models\Vendor;
use App\Policies\VendorPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['candidates'],
        properties: [
            new OA\Property(
                property: 'candidates',
                type: 'array',
                items: new OA\Items(
                    type: 'string',
                    format: 'uuid',
                ),
                minItems: 1
            ),
        ]
    )
)]
class AssignmentAddCandidatesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'candidates' => ['required', 'array', 'min:1'],
            'candidates.*' => [
                'uuid',
                function ($attribute, $value, $fail) {
                    $exists = Vendor::withGlobalScope('policy', VendorPolicy::scope())
                        ->where('id', $value)->exists();

                    if (! $exists) {
                        $fail('Vendor with such ID is not exists.');
                    }

                    $candidateExists = Candidate::where('assignment_id', $this->route('id'))
                        ->where('vendor_id', $value)->exists();

                    if ($candidateExists) {
                        $fail('Selected vendor is already candidate.');
                    }
                },
            ],
        ];
    }
}
