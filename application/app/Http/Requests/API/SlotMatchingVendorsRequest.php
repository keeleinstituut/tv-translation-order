<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

class SlotMatchingVendorsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'language_id' => ['required', 'uuid'],
            'start_at'    => ['required', 'date'],
            'end_at'      => ['required', 'date', 'after:start_at'],
        ];
    }
}
