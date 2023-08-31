<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = Project::getModel()
            ->with('typeClassifierValue')
            ->with('subProjects')
            ->with('subProjects.sourceLanguageClassifierValue')
            ->with('subProjects.destinationLanguageClassifierValue')
            ->paginate();

        return ProjectResource::collection($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $params = collect($request->all());

        return DB::transaction(function () use ($params) {
            $project = new Project();
            $project->institution_id = $params->get('institution_id');
            $project->type_classifier_value_id = $params->get('type_classifier_value_id');
            $project->reference_number = $params->get('reference_number');
            $project->workflow_template_id = 'Sample-project';
            $project->deadline_at = $params->get('deadline_at');
            $project->save();

            collect($params->get('source_files'))->each(function ($file) use ($project) {
                $project->addMedia($file)
                    ->toMediaCollection(Project::SOURCE_FILES_COLLECTION);
            });

            collect($params->get('help_files', []))->each(function ($file, $i) use ($project, $params) {
                $type = $params->get('help_file_types')[$i];
                $project->addMedia($file)
                    ->withCustomProperties([
                        'type' => $type,
                    ])
                    ->toMediaCollection(Project::HELP_FILES_COLLECTION);
            });

            $project->initSubProjects($params->get('source_language_classifier_value_id'), $params->get('destination_language_classifier_value_id'));
            $project->workflow()->startProcessInstance();

            $project->refresh();
            $project->load('subProjects', 'sourceFiles', 'helpFiles');

            return new ProjectResource($project);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = Project::getModel()
            ->with('subProjects')
            ->with('sourceFiles')
            ->with('helpFiles')
            ->with('typeClassifierValue', 'typeClassifierValue.projectTypeConfig')
            ->with('subProjects.sourceLanguageClassifierValue')
            ->with('subProjects.destinationLanguageClassifierValue')
            ->find($id);

        return new ProjectResource($data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
