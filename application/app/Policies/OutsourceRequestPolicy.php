<?php

namespace App\Policies;

use App\Enums\OutsourceRequestMode;
use App\Enums\OutsourceOfferStatus;
use App\Enums\OutsourceRequestStatus;
use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Models\Assignment;
use App\Models\AuthUser;
use App\Models\OutsourceOffer;
use App\Models\OutsourceRequest;

class OutsourceRequestPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->hasAtLeastOnePrivilege([
            PrivilegeKey::ManageOutsourceRequest,
            PrivilegeKey::ViewOutsourceRequest,
            PrivilegeKey::RespondOutsourceRequest,
        ]);
    }

    public function view(AuthUser $user, OutsourceRequest $request): bool
    {
        $project = $request->assignment->project;

        if ($user->isInSameInstitutionAsProject($project)) {
            return $user->hasPrivilege(PrivilegeKey::ManageOutsourceRequest);
        }

        if (! $user->hasAtLeastOnePrivilege([
            PrivilegeKey::ViewOutsourceRequest,
            PrivilegeKey::RespondOutsourceRequest,
        ])) {
            return false;
        }

        return OutsourceOffer::query()
            ->where('outsource_request_id', $request->id)
            ->where('institution_id', $user->institutionId)
            ->exists();
    }

    public function create(AuthUser $user, Assignment $assignment): bool
    {
        if (! $user->isInSameInstitutionAsProject($assignment->project)) {
            return false;
        }

        if (! $user->hasPrivilege(PrivilegeKey::ManageOutsourceRequest)) {
            return false;
        }

        return ! $user->belongsToTranslationAgency();
    }

    public function update(AuthUser $user, OutsourceRequest $request): bool
    {
        if (! $this->isOwnerWithManagePrivilege($user, $request)) {
            return false;
        }

        return $request->status === OutsourceRequestStatus::Active
            && $request->mode === OutsourceRequestMode::Cascade;
    }

    public function cancel(AuthUser $user, OutsourceRequest $request): bool
    {
        return $this->isOwnerWithManagePrivilege($user, $request);
    }

    public function select(AuthUser $user, OutsourceRequest $request): bool
    {
        return $this->isOwnerWithManagePrivilege($user, $request);
    }

    public function accept(AuthUser $user, OutsourceRequest $request): bool
    {
        return $user->hasPrivilege(PrivilegeKey::RespondOutsourceRequest)
            && $request->offers->contains('institution_id', $user->institutionId);
    }

    public function decline(AuthUser $user, OutsourceRequest $request): bool
    {
        return $user->hasPrivilege(PrivilegeKey::RespondOutsourceRequest)
            && $request->offers->contains('institution_id', $user->institutionId);
    }

    public function downloadMedia(AuthUser $user, OutsourceRequest $request): bool
    {
        $project = $request->assignment->project;

        if ($user->isInSameInstitutionAsProject($project)) {
            return $user->hasPrivilege(PrivilegeKey::ManageOutsourceRequest);
        }

        if (! $user->hasPrivilege(PrivilegeKey::ViewOutsourceRequest)) {
            return false;
        }

        if (OutsourceOffer::query()
            ->where('outsource_request_id', $request->id)
            ->where('institution_id', $user->institutionId)
            ->where('status', OutsourceOfferStatus::OfferAccepted)
            ->exists()) {
            return true;
        }

        if ($project->status === ProjectStatus::Accepted) {
            return false;
        }

        if (! $request->include_source_files) {
            return false;
        }

        return OutsourceOffer::query()
            ->where('outsource_request_id', $request->id)
            ->where('institution_id', $user->institutionId)
            ->whereIn('status', [
                OutsourceOfferStatus::RequestSent,
                OutsourceOfferStatus::RequestAccepted,
                OutsourceOfferStatus::OfferAccepted
            ])
            ->exists();
    }

    private function isOwnerWithManagePrivilege(AuthUser $user, OutsourceRequest $request): bool
    {
        return $user->isInSameInstitutionAsProject($request->assignment->project) &&
            $user->hasPrivilege(PrivilegeKey::ManageOutsourceRequest);
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
    public static function scope(): Scope\OutsourceRequestScope
    {
        return new Scope\OutsourceRequestScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class OutsourceRequestScope implements IScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $institutionId = Auth::user()->institutionId;
        $builder->where(function (Builder $q) use ($institutionId) {
            $q->whereHas('assignment.project',
                    fn (Builder $p) => $p->where('institution_id', $institutionId))
                ->orWhereHas('offers',
                    fn (Builder $r) => $r->where('institution_id', $institutionId));
        });
    }
}
