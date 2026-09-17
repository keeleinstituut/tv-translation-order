<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuthUser;
use App\Models\Project;
use App\Models\SubProject;
use App\Sync\ApiClients\TvAuthorizationApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CattoAuthorizationController extends Controller
{
    /**
     * Live authorization check called by Catto's backend (via a dedicated
     * service-account role, see EnsureJwtBelongsToServiceAccountWithCattoAuthorizationRole)
     * to decide whether a given person may access a project's CAT-tool data. Runs the
     * real ProjectPolicy against the real Project — nothing about the decision is
     * replicated in Catto.
     */
    public function check(Request $request, TvAuthorizationApiClient $client)
    {
        $params = $request->validate([
            'catto_project_id' => ['required', 'string'],
            'personal_identification_code' => ['required', 'string'],
            'ability' => ['required', 'string', 'in:view,update'],
        ]);

        $project = SubProject::query()
            ->where('cat_metadata->catto_project_id', $params['catto_project_id'])
            ->first()
            ?->project;

        if (empty($project)) {
            return response()->json(['allowed' => false]);
        }

        $institutionIds = collect(
            data_get(
                $client->getUserPrivileges($params['personal_identification_code']),
                'institutionPrivileges',
                []
            )
        )->keys();

        foreach ($institutionIds as $institutionId) {
            // ProjectPolicy's own-institution-privilege branches (e.g. ViewInstitutionProjectDetail)
            // don't re-check the institution match themselves — in the normal flow they're only ever
            // evaluated against a Project already fetched through ProjectPolicy::scope(), which does
            // that filtering. Since this endpoint fetches the Project directly (not through that
            // scoped query), replicate the same visibility precondition here before authorizing,
            // mirroring Scope\ProjectScope::apply() exactly.
            if (! $this->isProjectVisibleToInstitution($project, $institutionId)) {
                continue;
            }

            $claims = $client->getJwtClaims($params['personal_identification_code'], $institutionId);
            $authUser = new AuthUser($claims);

            if (Gate::forUser($authUser)->allows($params['ability'], $project)) {
                return response()->json(['allowed' => true]);
            }
        }

        return response()->json(['allowed' => false]);
    }

    private function isProjectVisibleToInstitution(Project $project, string $institutionId): bool
    {
        if ($project->institution_id === $institutionId) {
            return true;
        }

        return $project->subProjects()
            ->whereHas('assignments', fn ($q) => $q->sharedWithInstitution($institutionId))
            ->exists();
    }
}
