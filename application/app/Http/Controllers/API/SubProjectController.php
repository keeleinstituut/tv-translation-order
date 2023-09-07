<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\SubProjectResource;
use App\Models\Project;
use App\Models\SubProject;
use Illuminate\Http\Request;

class SubProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = SubProject::getModel()
            ->paginate();

        return SubProjectResource::collection($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = SubProject::getModel()
            ->with('sourceLanguageClassifierValue')
            ->with('destinationLanguageClassifierValue')
            ->with('sourceFiles')
//            ->with('project.typeClassifierValue.projectTypeConfig')
            ->with('assignments.candidates.vendor.institutionUser')
            ->with('assignments.assignee.institutionUser')
            ->find($id) ?? abort(404);

        return new SubProjectResource($data);
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

    public function sendToCat(string $id)
    {
        $subProject = SubProject::find($id);
        if (collect($subProject->cat_metadata)->isNotEmpty()) {
            abort(400, 'Cat project already created');
        }

        return $subProject->cat()->setupCatToolJobs();
    }

    public function sendToWork(string $id)
    {
        $subProject = SubProject::find($id);
    }
}
