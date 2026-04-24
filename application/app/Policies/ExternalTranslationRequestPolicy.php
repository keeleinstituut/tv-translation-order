<?php

namespace App\Policies;

use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\InstitutionType;
use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Models\Assignment;
use App\Models\AuthUser;
use App\Models\CachedEntities\Institution;
use App\Models\ExternalTranslationRequest;
use App\Models\ExternalTranslationRequestRecipient;

class ExternalTranslationRequestPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->hasAtLeastOnePrivilege([
            PrivilegeKey::ManageExternalTranslationRequest,
            PrivilegeKey::ViewExternalTranslationRequest,
            PrivilegeKey::RespondExternalTranslationRequest,
        ]);
    }

    public function view(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        $project = $request->assignment->subProject->project;

        if ($user->isInSameInstitutionAs($project)) {
            return $user->hasPrivilege(PrivilegeKey::ManageExternalTranslationRequest);
        }

        if (! $user->hasPrivilege(PrivilegeKey::ViewExternalTranslationRequest)) {
            return false;
        }

        return ExternalTranslationRequestRecipient::query()
            ->where('external_translation_request_id', $request->getKey())
            ->where('institution_id', $user->institutionId)
            ->exists();
    }

    public function create(AuthUser $user, Assignment $assignment): bool
    {
        if (! $user->isInSameInstitutionAs($assignment->subProject->project)) {
            return false;
        }

        if (! $user->hasPrivilege(PrivilegeKey::ManageExternalTranslationRequest)) {
            return false;
        }

        return ! Institution::where('id', $user->institutionId)
            ->where('institution_type', InstitutionType::TranslationAgency)
            ->exists();
    }

    public function update(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        return $this->isOwnerWithManagePrivilege($user, $request);
    }

    public function cancel(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        return $this->isOwnerWithManagePrivilege($user, $request);
    }

    public function select(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        return $this->isOwnerWithManagePrivilege($user, $request);
    }

    public function downloadMedia(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        $project = $request->assignment->subProject->project;

        if ($user->isInSameInstitutionAs($project)) {
            return $user->hasPrivilege(PrivilegeKey::ManageExternalTranslationRequest);
        }

        if ($project->status === ProjectStatus::Accepted) {
            return false;
        }

        if (! $user->hasPrivilege(PrivilegeKey::ViewExternalTranslationRequest)) {
            return false;
        }

        return ExternalTranslationRequestRecipient::query()
            ->where('external_translation_request_id', $request->getKey())
            ->where('institution_id', $user->institutionId)
            ->whereIn('status', ExternalRequestRecipientStatus::activeForPartner())
            ->exists();
    }

    private function isOwnerWithManagePrivilege(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        return $user->isInSameInstitutionAs($request->assignment->subProject->project) &&
            $user->hasPrivilege(PrivilegeKey::ManageExternalTranslationRequest);
    }

    // Should serve as an query enhancement to Eloquent queries
    // to filter out objects that the user does not have permissions to.
    //
    // Example usage in query:
    // Role::getModel()->withGlobalScope('policy', RolePolicy::scope())->get();
    //
    // The 'policy' string in the example is not strict and is used internally to identify
    // the scope applied in Eloquent querybuilder. It can be something else as well,
    // but it should correspond with the intentions of the scope. Using 'policy' provides
    // general understanding throughout the whole project that the applied scope is related to policy.
    // The withGlobalScope method does not apply the scope globally, it applies to only the querybuilder
    // of current query. The method name could be different, but in the sake of reusability
    // we can use this method that's provided by Laravel and used internally.
    //
    public static function scope(): Scope\ExternalTranslationRequestScope
    {
        return new Scope\ExternalTranslationRequestScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class ExternalTranslationRequestScope implements IScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $institutionId = Auth::user()->institutionId;
        $builder->where(function (Builder $q) use ($institutionId) {
            $q->whereHas('assignment.subProject.project',
                    fn (Builder $p) => $p->where('institution_id', $institutionId))
                ->orWhereHas('recipients',
                    fn (Builder $r) => $r->where('institution_id', $institutionId));
        });
    }
}
