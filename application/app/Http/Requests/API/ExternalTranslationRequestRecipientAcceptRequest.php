<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class ExternalTranslationRequestRecipientAcceptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'proposed_price' => 'nullable|numeric|min:0',
            'response_comment' => 'nullable|string',
        ];
    }
}
