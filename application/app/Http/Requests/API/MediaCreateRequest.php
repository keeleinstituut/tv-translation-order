<?php

namespace App\Http\Requests\API;

use App\Models\Assignment;
use App\Models\Project;
use App\Policies\AssignmentPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(
            required: ['files'],
            properties: [
                new OA\Property(
                    property: 'files',
                    type: 'array',
                    items: new OA\Items(
                        required: ['content', 'collection', 'reference_object_id', 'reference_object_type'],
                        properties: [
                            new OA\Property(property: 'content', type: 'string', format: 'binary'),
                            new OA\Property(property: 'collection', type: 'string'),
                            new OA\Property(property: 'reference_object_id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'reference_object_type', type: 'string'),
                            new OA\Property(property: 'help_file_type', type: 'string'),
                            new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid', nullable: true),
                        ]
                    ),
                    minItems: 1
                ),
            ]
        )
    )
)]
class MediaCreateRequest extends FormRequest
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
            'files.*.content' => 'required|file',
            'files.*.collection' => ['required', 'string', Rule::in([Project::SOURCE_FILES_COLLECTION, Project::FINAL_FILES_COLLECTION, Project::HELP_FILES_COLLECTION])],
            'files.*.reference_object_id' => 'required|uuid',
            'files.*.reference_object_type' => ['required', 'string', Rule::in(['project', 'subproject'])],
            'files.*.assignment_id' => ['sometimes', 'uuid'],
            'files.*.help_file_type' => ['required_if:collection,' . Project::HELP_FILES_COLLECTION, Rule::in(Project::HELP_FILE_TYPES)]
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

                     if ($fileData['collection'] === Project::HELP_FILES_COLLECTION && empty($fileData['help_file_type'])) {
                         $validator->errors()->add("files.$idx.help_file_type", 'File type is required');
                     }

                     if (filled($fileData['assignment_id'] ?? '')) {
                         $assignment = Assignment::withGlobalScope('policy', AssignmentPolicy::scope())
                             ->find($fileData['assignment_id']);

                         if (empty($assignment)) {
                             $validator->errors()->add("files.$idx.assignment_id", 'Assignment not found');
                         }

                         if ($fileData['reference_object_type'] === 'subproject' && $assignment->sub_project_id !== $fileData['reference_object_id']) {
                             $validator->errors()->add("files.$idx.assignment_id", 'Assignment belongs to another project');
                         }

                         if ($fileData['reference_object_type'] === 'project' && $assignment->subProject->project_id !== $fileData['reference_object_id']) {
                             $validator->errors()->add("files.$idx.assignment_id", 'Assignment belongs to another project ' . $assignment->subProject->project_id. ' ' . $fileData['reference_object_id']);
                         }
                     }
                });
            }
        ];
    }
}
