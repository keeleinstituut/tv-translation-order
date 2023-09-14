<?php

namespace App\Models;

use App\Models\CachedEntities\ClassifierValue;
use App\Services\CatPickerService;
use ArrayObject;
use Database\Factories\SubProjectFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Staudenmeir\EloquentHasManyDeep\Eloquent\CompositeKey;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;
use Throwable;

/**
 * App\Models\SubProject
 *
 * @property string|null $id
 * @property string|null $ext_id
 * @property string|null $project_id
 * @property string|null $file_collection
 * @property string|null $file_collection_final
 * @property string|null $matecat_job_id
 * @property string|null $workflow_ref
 * @property string|null $source_language_classifier_value_id
 * @property string|null $destination_language_classifier_value_id
 * @property ArrayObject|null $cat_metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Assignment> $assignments
 * @property-read int|null $assignments_count
 * @property-read ClassifierValue|null $destinationLanguageClassifierValue
 * @property-read Project|null $project
 * @property-read ClassifierValue|null $sourceLanguageClassifierValue
 *
 * @method static SubProjectFactory factory($count = null, $state = [])
 * @method static Builder|SubProject newModelQuery()
 * @method static Builder|SubProject newQuery()
 * @method static Builder|SubProject query()
 * @method static Builder|SubProject whereCatMetadata($value)
 * @method static Builder|SubProject whereCreatedAt($value)
 * @method static Builder|SubProject whereDestinationLanguageClassifierValueId($value)
 * @method static Builder|SubProject whereExtId($value)
 * @method static Builder|SubProject whereFileCollection($value)
 * @method static Builder|SubProject whereFileCollectionFinal($value)
 * @method static Builder|SubProject whereId($value)
 * @method static Builder|SubProject whereMatecatJobId($value)
 * @method static Builder|SubProject whereProjectId($value)
 * @method static Builder|SubProject whereSourceLanguageClassifierValueId($value)
 * @method static Builder|SubProject whereUpdatedAt($value)
 * @method static Builder|SubProject whereWorkflowRef($value)
 *
 * @mixin Eloquent
 */
class SubProject extends Model
{
    use HasFactory;
    use HasUuids;
    use HasRelationships;

    protected $guarded = [];

    protected $casts = [
        'cat_metadata' => AsArrayObject::class,
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function sourceLanguageClassifierValue()
    {
        return $this->belongsTo(ClassifierValue::class, 'source_language_classifier_value_id');
    }

    public function destinationLanguageClassifierValue()
    {
        return $this->belongsTo(ClassifierValue::class, 'destination_language_classifier_value_id');
    }

    public function sourceFiles()
    {
        return $this->hasManyDeep(
            Media::class,
            [SubProject::class],
            ['id', new CompositeKey('model_id', 'collection_name')],
            ['id', new CompositeKey('project_id', 'file_collection')]
        );
    }

    public function projectTypeConfig()
    {
        return $this->hasOneDeep(
            ProjectTypeConfig::class,
            [Project::class, ClassifierValue::class],
            ['id', 'id', 'type_classifier_value_id'],
            ['project_id', 'type_classifier_value_id', 'id']
        );
    }

    public function finalFiles()
    {
        return $this->hasManyDeep(
            Media::class,
            [SubProject::class],
            ['id', new CompositeKey('model_id', 'collection_name')],
            ['id', new CompositeKey('project_id', 'file_collection_final')]
        );
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    /** @throws Throwable */
    public function initAssignments()
    {
        collect($this->project->typeClassifierValue->projectTypeConfig->features)
            ->filter(fn ($elem) => Str::startsWith($elem, 'job'))
            ->each(function ($feature) {
                $assignment = new Assignment();
                $assignment->sub_project_id = $this->id;
                $assignment->feature = $feature;
                $assignment->saveOrFail();
            });
    }

    //    public function sourceFiles2() {
    //        return $this->project->media()->where('collection_name', $this->file_collection);
    //    }

    public function cat()
    {
        $catClass = CatPickerService::pick(CatPickerService::MATECAT);

        return new $catClass($this);
    }
}
