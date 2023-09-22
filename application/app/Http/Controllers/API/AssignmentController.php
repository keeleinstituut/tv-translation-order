<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\AssignmentResource;
use App\Http\Resources\API\VolumeResource;
use App\Models\Assignment;
use App\Policies\AssignmentPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AssignmentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @throws AuthorizationException
     */
    public function index(Request $request): ResourceCollection
    {
        $this->authorize('viewAny', Assignment::class);

        $data = static::getBaseQuery()->where(
            'sub_project_id',
            $request->route('subProjectId')
        )->with(
            'candidates.vendor.institutionUser',
            'assignee.vendor.institutionUser',
            'volumes',
            'catToolJobs'
        );

        return AssignmentResource::collection($data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // create assignment under subproject?
    }

    /**
     * Display the specified resource.
     *
     * @throws AuthorizationException
     */
    public function show(string $id): AssignmentResource
    {
        $assignment = static::getBaseQuery()->findOrFail($id);
        $this->authorize('view', $assignment);

        return AssignmentResource::make($assignment);
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

    private static function getBaseQuery(): Assignment|Builder
    {
        return Assignment::getModel()->withGlobalScope('policy', AssignmentPolicy::scope());
    }
}
