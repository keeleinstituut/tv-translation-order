<?php

namespace App\Http\Requests\API;

use App\Models\Media;
use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SetProjectFinalFilesRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'final_file_id' => ['array'],
            'final_file_id.*' => [
                'required',
                'integer',
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

                $subProject = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())->find($this->route('id'));
                if (empty($subProject)) {
                    return;
                }

                $subProjectFinalFilesIds = $subProject->finalFiles->pluck('id');
                collect($this->validated('final_file_id'))->each(function (int $finalFileId, int $idx) use ($subProjectFinalFilesIds, $validator) {
                    if (! $subProjectFinalFilesIds->contains($finalFileId)) {
                        $validator->errors()->add("final_file_id.$idx", 'The file is not subproject final file');
                    }
                });
            }
        ];
    }
}
