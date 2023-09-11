<?php

namespace App\Http\Requests\API;

use App\Models\Media;
use App\Models\Project;
use App\Models\SubProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatToolSetupRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'sub_project_id' => [
                'required',
                'uuid',
                Rule::exists(SubProject::class, 'id'),
            ],
            'source_files_ids' => ['required', 'array'],
            'source_files_ids.*' => [
                'required',
                'string',
                'bail',
                Rule::exists(Media::class, 'id')->where('collection_name', Project::SOURCE_FILES_COLLECTION),
            ],
        ];
    }
}
