<?php

namespace App\Http\Requests\API;

use App\Models\Candidate;
use App\Models\Vendor;
use App\Policies\VendorPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['data'],
        properties: [
            new OA\Property(
                property: 'data',
                type: 'array',
                items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid'),
                    ]
                ),
                minItems: 1
            ),
        ]
    )
)]
class AssignmentDeleteCandidateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'data' => ['required', 'array', 'min:1'],
            'data.*.vendor_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) {
                    try {
                        Uuid::fromString($value);
                    } catch (\Exception $e) {
                        return;
                    }

                    $vendorExists = Vendor::withGlobalScope('policy', VendorPolicy::scope())
                        ->where('id', $value)->exists();

                    if (! $vendorExists) {
                        $fail('Vendor with such ID does not exist');
                    }

                    $candidateExists = Candidate::where('assignment_id', $this->route('id'))
                        ->where('vendor_id', $value)->exists();

                    if (! $candidateExists) {
                        $fail('Candidate for vendor does not exist');
                    }
                },
            ],
        ];
    }
}
