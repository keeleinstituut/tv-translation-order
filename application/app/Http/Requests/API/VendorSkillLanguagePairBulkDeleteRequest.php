<?php

namespace App\Http\Requests\API;

use App\Models\VendorSkillLanguagePair;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorSkillLanguagePairBulkDeleteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required|array|min:1',
            'id.*' => [
                'uuid',
                'distinct',
                Rule::exists(VendorSkillLanguagePair::class, 'id'),
            ],
        ];
    }
}
