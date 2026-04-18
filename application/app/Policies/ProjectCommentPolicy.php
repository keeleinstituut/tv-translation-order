<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;
use App\Models\Project;
use App\Models\ProjectComment;

class ProjectCommentPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return true;
    }

    public function create(AuthUser $user, Project $project): bool
    {
        return $user->hasAtLeastOnePrivilege([PrivilegeKey::ManageProject, PrivilegeKey::CreateProject]) ||
            $project->assignees()->where('institution_user_id', $user->institutionUserId)->exists();
    }

    public function update(AuthUser $user, ProjectComment $projectComment): bool
    {
        return $user->hasPrivilege(PrivilegeKey::ManageProject)
            && $projectComment->institution_user_id === $user->institutionUserId;
    }

    public function delete(AuthUser $user, ProjectComment $projectComment): bool
    {
        return $user->hasPrivilege(PrivilegeKey::ManageProject);
    }

    public static function scope()
    {
        return new Scope\ProjectCommentScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use App\Policies\ProjectPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;

class ProjectCommentScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('project', function (Builder $projectQuery) {
            $projectQuery->withGlobalScope('policy', ProjectPolicy::scope());
        });
    }
}
