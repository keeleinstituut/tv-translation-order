<?php

namespace App\Models;

use App\Models\CachedEntities\InstitutionUser;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Staudenmeir\EloquentHasManyDeep\Eloquent\CompositeKey;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

/**
 * App\Models\ProjectReviewRejection
 *
 * @property int $id
 * @property string $project_id
 * @property string $institution_user_id
 * @property string $description
 * @property mixed $sub_project_ids
 * @property string $file_collection
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder|ProjectReviewRejection newModelQuery()
 * @method static Builder|ProjectReviewRejection newQuery()
 * @method static Builder|ProjectReviewRejection query()
 * @method static Builder|ProjectReviewRejection whereCreatedAt($value)
 * @method static Builder|ProjectReviewRejection whereDescription($value)
 * @method static Builder|ProjectReviewRejection whereFileCollection($value)
 * @method static Builder|ProjectReviewRejection whereId($value)
 * @method static Builder|ProjectReviewRejection whereInstitutionUserId($value)
 * @method static Builder|ProjectReviewRejection whereSubProjectIds($value)
 * @method static Builder|ProjectReviewRejection whereUpdatedAt($value)
 * @mixin Eloquent
 */
class ProjectReviewRejection extends Model
{
    use HasFactory;
    use HasUuids;
    use HasRelationships;

    protected $casts = [
        'sub_project_ids' => AsArrayObject::class,
    ];

    protected $guarded = [];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function institutionUser(): BelongsTo
    {
        return $this->belongsTo(InstitutionUser::class);
    }

    public function files(): HasManyDeep
    {
        return $this->hasManyDeep(
            Media::class,
            [ProjectReviewRejection::class],
            ['id', new CompositeKey('model_id', 'collection_name')],
            ['id', new CompositeKey('project_id', 'file_collection')]
        );
    }

    public function getSubProjects(): Collection
    {
        if (empty($this->sub_project_ids)) {
            return collect();
        }

        return SubProject::whereIn('id', $this->sub_project_ids)->get();
    }
}
