<?php

namespace App\Http\Requests\API;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaDownloadRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'collection' => 'required|string',
            'reference_object_id' => 'required|uuid',
            'reference_object_type' => 'required|string',
            'id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Media::class, 'id'),
            ],
        ];
    }
}
