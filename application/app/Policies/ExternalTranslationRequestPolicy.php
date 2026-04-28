<?php

namespace App\Policies;

use App\Enums\ExternalRequestMode;
use App\Enums\ExternalRequestRecipientStatus;
use App\Enums\ExternalRequestStatus;
use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Models\Assignment;
use App\Models\AuthUser;
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
        $project = $request->assignment->project;

        if ($user->isInSameInstitutionAs($project)) {
            return $user->hasPrivilege(PrivilegeKey::ManageExternalTranslationRequest);
        }

        if (! $user->hasAtLeastOnePrivilege([
            PrivilegeKey::ViewExternalTranslationRequest,
            PrivilegeKey::RespondExternalTranslationRequest,
        ])) {
            return false;
        }

        return ExternalTranslationRequestRecipient::query()
            ->where('external_translation_request_id', $request->id)
            ->where('institution_id', $user->institutionId)
            ->exists();
    }

    public function create(AuthUser $user, Assignment $assignment): bool
    {
        if (! $user->isInSameInstitutionAs($assignment->project)) {
            return false;
        }

        if (! $user->hasPrivilege(PrivilegeKey::ManageExternalTranslationRequest)) {
            return false;
        }

        return ! $user->belongsToTranslationAgency();
    }

    public function update(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        if (! $this->isOwnerWithManagePrivilege($user, $request)) {
            return false;
        }

        return $request->status === ExternalRequestStatus::Active
            && $request->mode === ExternalRequestMode::Cascade;
    }

    public function cancel(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        return $this->isOwnerWithManagePrivilege($user, $request);
    }

    public function select(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        return $this->isOwnerWithManagePrivilege($user, $request);
    }

    public function accept(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        return $user->hasPrivilege(PrivilegeKey::RespondExternalTranslationRequest)
            && $request->recipients->contains('institution_id', $user->institutionId);
    }

    public function decline(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        return $user->hasPrivilege(PrivilegeKey::RespondExternalTranslationRequest)
            && $request->recipients->contains('institution_id', $user->institutionId);
    }

    public function downloadMedia(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        $project = $request->assignment->project;

        if ($user->isInSameInstitutionAs($project)) {
            return $user->hasPrivilege(PrivilegeKey::ManageExternalTranslationRequest);
        }

        if ($project->status === ProjectStatus::Accepted) {
            return false;
        }

        if (! $request->include_source_files) {
            return false;
        }

        if (! $user->hasPrivilege(PrivilegeKey::ViewExternalTranslationRequest)) {
            return false;
        }

        return ExternalTranslationRequestRecipient::query()
            ->where('external_translation_request_id', $request->id)
            ->where('institution_id', $user->institutionId)
            ->whereIn('status', [
                ExternalRequestRecipientStatus::Notified,
                ExternalRequestRecipientStatus::Accepted,
                ExternalRequestRecipientStatus::Selected
            ])
            ->exists();
    }

    private function isOwnerWithManagePrivilege(AuthUser $user, ExternalTranslationRequest $request): bool
    {
        return $user->isInSameInstitutionAs($request->assignment->project) &&
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
            $q->whereHas('assignment.project',
                    fn (Builder $p) => $p->where('institution_id', $institutionId))
                ->orWhereHas('recipients',
                    fn (Builder $r) => $r->where('institution_id', $institutionId));
        });
    }
}
