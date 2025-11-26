<?php

namespace App\Models;

use App\Enums\TagType;
use App\Models\CachedEntities\Institution;
use Database\Factories\TagFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * App\Models\Tag
 *
 * @property string|null $id
 * @property string|null $name
 * @property TagType|null $type
 * @property string|null $institution_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read Collection<int, Vendor> $vendors
 * @property-read int|null $vendors_count
 * @property-read Collection<int, Project> $projects
 * @property-read int|null $projects_count
 * @property-read Institution|null $institution
 *
 * @method static TagFactory factory($count = null, $state = [])
 * @method static Builder|Tag newModelQuery()
 * @method static Builder|Tag newQuery()
 * @method static Builder|Tag query()
 * @method static Builder|Tag whereCreatedAt($value)
 * @method static Builder|Tag whereDeletedAt($value)
 * @method static Builder|Tag whereId($value)
 * @method static Builder|Tag whereInstitutionId($value)
 * @method static Builder|Tag whereName($value)
 * @method static Builder|Tag whereType($value)
 * @method static Builder|Tag whereUpdatedAt($value)
 * @method static Builder|Tag onlyTrashed()
 * @method static Builder|Tag withTrashed()
 * @method static Builder|Tag withoutTrashed()
 *
 * @mixin Eloquent
 */
class Tag extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'tags';

    protected $fillable = [
        'name',
        'type',
        'institution_id',
    ];

    protected $casts = [
        'type' => TagType::class,
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function vendors(): MorphToMany
    {
        return $this->morphedByMany(Vendor::class, 'taggable')->using(Taggable::class);
    }

    public function projects(): MorphToMany
    {
        return $this->morphedByMany(Project::class, 'taggable')->using(Taggable::class);
    }
}
