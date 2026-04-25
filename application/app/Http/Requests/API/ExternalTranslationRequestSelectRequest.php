<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class ExternalTranslationRequestSelectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'recipient_id' => 'required|uuid',
        ];
    }
}
