<?php

namespace App\Http\Controllers\API;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\OpenApiHelpers as OAH;
use App\Http\Requests\API\ProjectCreateRequest;
use App\Http\Resources\API\ProjectResource;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/projects',
        summary: 'Create a new project',
        requestBody: new OAH\RequestBody(ProjectCreateRequest::class),
        responses: [new OAH\Forbidden, new OAH\Unauthorized, new OAH\Invalid]
    )]
    #[OAH\ResourceResponse(dataRef: ProjectResource::class, description: 'Created project', response: Response::HTTP_CREATED)]
    public function store(ProjectCreateRequest $request): ProjectResource
    {
        return DB::transaction(function () use ($request) {
            $project = Project::make([
                'institution_id' => Auth::user()->institutionId,
                'type_classifier_value_id' => $request->validated('type_classifier_value_id'),
                'translation_domain_classifier_value_id' => $request->validated('translation_domain_classifier_value_id'),
                'reference_number' => $request->validated('reference_number'),
                'manager_institution_user_id' => $request->validated('manager_institution_user_id'),
                'client_institution_user_id' => $request->validated('client_institution_user_id', Auth::user()->institutionUserId),
                'deadline_at' => $request->validated('deadline_at'),
                'comments' => $request->validated('comments'),
                'event_start_at' => $request->validated('event_start_at'),
                'workflow_template_id' => Config::get('app.workflows.process_definitions.project'),
                'status' => $request->safe()->has('manager_institution_user_id')
                    ? ProjectStatus::Registered
                    : ProjectStatus::New,
            ]);

            $this->authorize('create', $project);

            $project->saveOrFail();

            collect($request->validated('source_files', []))
                ->each(function (UploadedFile $file) use ($project) {
                    $project->addMedia($file)->toMediaCollection(Project::SOURCE_FILES_COLLECTION);
                });

            collect($request->validated('help_files', []))
                ->zip($request->validated('help_file_types', []))
                ->eachSpread(function (UploadedFile $file, string $type) use ($project) {
                    $project->addMedia($file)
                        ->withCustomProperties(['type' => $type])
                        ->toMediaCollection(Project::HELP_FILES_COLLECTION);
                });

            $project->initSubProjects(
                ClassifierValue::findOrFail($request->validated('source_language_classifier_value_id')),
                ClassifierValue::findMany($request->validated('destination_language_classifier_value_ids'))
            );

            $project->workflow()->startProcessInstance();

            return new ProjectResource($project->refresh()->load('media', 'managerInstitutionUser', 'clientInstitutionUser', 'typeClassifierValue', 'translationDomainClassifierValue', 'subProjects'));
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
