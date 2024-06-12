<?php

namespace App\Http\Requests\API;

use App\Models\Media;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MediaDownloadRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'collection' => ['required', 'string', Rule::in([Project::SOURCE_FILES_COLLECTION, Project::FINAL_FILES_COLLECTION, Project::HELP_FILES_COLLECTION, Project::REVIEW_FILES_COLLECTION_PREFIX])],
            'reference_object_id' => 'required|uuid',
            'reference_object_type' => ['required', 'string', Rule::in(['project', 'subproject', 'review'])],
            'id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(Media::class, 'id'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                collect($this->validated('files'))->each(function (array $fileData, int $idx) use ($validator) {
                    if (!in_array([$fileData['reference_object_type'], $fileData['collection']], [
                        ['project', 'source'],
                        ['project', 'help'],
                        ['subproject', 'source'],
                        ['subproject', 'final'],
                    ])) {
                        $validator->errors()->add("files.$idx.collection", 'Such collection is not available for the specified entity');
                    }
                });
            }
        ];
    }
}
