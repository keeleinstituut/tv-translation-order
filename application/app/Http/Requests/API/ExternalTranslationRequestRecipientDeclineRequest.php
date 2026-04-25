<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class ExternalTranslationRequestRecipientDeclineRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'decline_comment' => 'required|string',
        ];
    }
}
