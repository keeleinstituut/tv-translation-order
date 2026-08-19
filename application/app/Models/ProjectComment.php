<?php

namespace App\Models;

use App\Models\CachedEntities\InstitutionUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $project_id
 * @property string $comment
 * @property string $institution_user_id
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InstitutionUser $institutionUser
 * @property-read \App\Models\Project $project
 * @method static \Database\Factories\ProjectCommentFactory factory($count = null, $state = [])
 * @method static Builder<static>|ProjectComment newModelQuery()
 * @method static Builder<static>|ProjectComment newQuery()
 * @method static Builder<static>|ProjectComment onlyTrashed()
 * @method static Builder<static>|ProjectComment query()
 * @method static Builder<static>|ProjectComment whereComment($value)
 * @method static Builder<static>|ProjectComment whereCreatedAt($value)
 * @method static Builder<static>|ProjectComment whereDeletedAt($value)
 * @method static Builder<static>|ProjectComment whereId($value)
 * @method static Builder<static>|ProjectComment whereInstitutionUserId($value)
 * @method static Builder<static>|ProjectComment whereProjectId($value)
 * @method static Builder<static>|ProjectComment whereUpdatedAt($value)
 * @method static Builder<static>|ProjectComment withTrashed(bool $withTrashed = true)
 * @method static Builder<static>|ProjectComment withoutTrashed()
 * @mixin \Eloquent
 */
class ProjectComment extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'project_comments';

    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function institutionUser(): BelongsTo
    {
        return $this->belongsTo(InstitutionUser::class);
    }
}
