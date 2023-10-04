<?php

namespace App\Http\Requests\API;

use App\Models\Vendor;
use App\Policies\VendorPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['vendor_id'],
        properties: [
            new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid'),
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
            'vendor_id' => [
                'required',
                'uuid',
                function ($attribute, $value, $fail) {
                    $exists = Vendor::withGlobalScope('policy', VendorPolicy::scope())
                        ->where('id', $value)->exists();

                    if (! $exists) {
                        $fail('Vendor with such ID doesn\'t exist.');
                    }
                },
            ],
        ];
    }
}
