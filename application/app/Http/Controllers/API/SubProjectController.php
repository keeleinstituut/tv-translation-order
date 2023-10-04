<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\SubProjectResource;
use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SubProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = SubProject::getModel()
            ->with('project')
            ->with('project.typeClassifierValue')
            ->with('sourceLanguageClassifierValue')
            ->with('destinationLanguageClassifierValue')
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

    private static function getBaseQuery(): Builder
    {
        return SubProject::withGlobalScope('policy', SubProjectPolicy::scope());
    }
}
