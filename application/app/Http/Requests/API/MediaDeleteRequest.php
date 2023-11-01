<?php

namespace App\Http\Requests\API;

use App\Models\Media;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['files'],
        properties: [
            new OA\Property(
                property: 'files',
                type: 'array',
                items: new OA\Items(
                    required: ['content', 'collection', 'reference_object_id', 'reference_object_type'],
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'collection', type: 'string'),
                        new OA\Property(property: 'reference_object_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'reference_object_type', type: 'string'),
                    ]
                ),
                minItems: 1
            ),
        ]
    )
)]
class MediaDeleteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'files' => 'required|array|min:1',
            'files.*.collection' => ['required', 'string', Rule::in([Project::SOURCE_FILES_COLLECTION, Project::FINAL_FILES_COLLECTION])],
            'files.*.reference_object_id' => 'required|uuid',
            'files.*.reference_object_type' => ['required', 'string', Rule::in(['project', 'subproject'])],
            'files.*.id' => [
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
                        ['subproject', 'source'],
                        ['subproject', 'final'],
                    ])) {
                        $validator->errors()->add("files.$idx.collection", 'Not possible to add Media into specified collection');
                    }
                });
            }
        ];
    }
}
