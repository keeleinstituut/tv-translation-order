<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Vendor;
use App\Models\InstitutionUser;

class VendorBulkCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'data' => 'required|array|min:1',
            'data.*.institution_user_id' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists(app(InstitutionUser::class)->getTable(), 'id'),
                Rule::unique(app(Vendor::class)->getTable(), 'institution_user_id'),
            ],
            'data.*.company_name' => [
                'string',
            ],
        ];
    }
}
