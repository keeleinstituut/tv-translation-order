<?php

namespace App\Models;

use App\Enums\SubProjectStatus;
use App\Models\CachedEntities\ClassifierValue;
use App\Services\CatTools\CatPickerService;
use App\Services\CatTools\Contracts\CatToolService;
use App\Services\Prices\PriceCalculator;
use App\Services\Prices\SubProjectPriceCalculator;
use ArrayObject;
use Database\Factories\SubProjectFactory;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;
use Spatie\MediaLibrary\InteractsWithMedia;
use Staudenmeir\EloquentHasManyDeep\Eloquent\CompositeKey;
use Staudenmeir\EloquentHasManyDeep\HasManyDeep;
use Staudenmeir\EloquentHasManyDeep\HasOneDeep;
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
 * @property boolean $workflow_started
 * @property string|null $source_language_classifier_value_id
 * @property string|null $destination_language_classifier_value_id
 * @property string|null $active_job_definition_id
 * @property ArrayObject|null $cat_metadata
 * @property float|null $price
 * @property SubProjectStatus|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $deadline_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Assignment> $assignments
 * @property-read int|null $assignments_count
 * @property-read ClassifierValue|null $destinationLanguageClassifierValue
 * @property-read Project|null $project
 * @property-read ClassifierValue|null $sourceLanguageClassifierValue
 * @property-read Collection<int, Media> $sourceFiles
 * @property-read Collection<int, Media> $finalFiles
 * @property-read Collection<int, CatToolJob> $catToolJobs
 * @property-read Collection<int, CatToolTmKey> $catToolTmKeys
 * @property-read ClassifierValue|null $translationDomainClassifierValue
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
 * @method static Builder|SubProject hasAnyOfLanguageDirections(array[] $languageDirections)
 *
 * @mixin Eloquent
 */
class SubProject extends Model
{
    use HasFactory;
    use HasUuids;
    use HasRelationships;
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'cat_metadata' => AsArrayObject::class,
        'price' => 'float',
        'status' => SubProjectStatus::class,
        'workflow_started' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function translationDomainClassifierValue(): HasOneDeep
    {
        return $this->hasOneDeepFromRelations(
            $this->project(),
            (new Project())->translationDomainClassifierValue()
        );
    }

    public function sourceLanguageClassifierValue(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'source_language_classifier_value_id');
    }

    public function destinationLanguageClassifierValue(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'destination_language_classifier_value_id');
    }

    public function sourceFiles(): HasManyDeep
    {
        return $this->hasManyDeep(
            Media::class,
            [SubProject::class],
            ['id', new CompositeKey('model_id', 'collection_name')],
            ['id', new CompositeKey('project_id', 'file_collection')]
        );
    }

    public function projectTypeConfig(): HasManyDeep
    {
        return $this->hasOneDeep(
            ProjectTypeConfig::class,
            [Project::class, ClassifierValue::class],
            ['id', 'id', 'type_classifier_value_id'],
            ['project_id', 'type_classifier_value_id', 'id']
        );
    }

    public function finalFiles(): HasManyDeep
    {
        return $this->hasManyDeep(
            Media::class,
            [SubProject::class],
            ['id', new CompositeKey('model_id', 'collection_name')],
            ['id', new CompositeKey('project_id', 'file_collection_final')]
        );
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function catToolJobs(): HasMany
    {
        return $this->hasMany(CatToolJob::class)->orderBy('id');
    }

    public function catToolTmKeys(): HasMany
    {
        return $this->hasMany(CatToolTmKey::class);
    }

    /** @throws Throwable */
    public function initAssignments(): void
    {
        $jobDefinitions = $this->project->typeClassifierValue->projectTypeConfig->jobDefinitions;

        if (empty($jobDefinitions)) {
            throw new RuntimeException("Assignments are not populated. Job definitions not found for project type " . $this->project->typeClassifierValue->value);
        }

        $jobDefinitions->each(function (JobDefinition $jobDefinition) {
            $assignment = new Assignment();
            $assignment->sub_project_id = $this->id;
            $assignment->job_definition_id = $jobDefinition->id;
            $assignment->saveOrFail();
        });
    }

    public function moveFinalFilesToProjectFinalFiles($finalFilesIds): void
    {
        if (empty($finalFilesIds)) {
            throw new InvalidArgumentException('No final files specified');
        }


        $files = $this->finalFiles->filter(fn (Media $media) => in_array($media->id, $finalFilesIds))
            ->values();

        $wrongFilesPassed = $files->count() !== count($finalFilesIds);

        if ($wrongFilesPassed) {
            throw new InvalidArgumentException('Files IDs are incorrect');
        }

        $project = $this->project;
        $files->each(function (Media $media) use ($project) {
            $media->copy($project, Project::FINAL_FILES_COLLECTION);
        });
    }

    public function cat(): CatToolService
    {
        return (new CatPickerService($this))->pick(CatPickerService::MATECAT);
    }

    public function getPriceCalculator(): PriceCalculator
    {
        return new SubProjectPriceCalculator($this);
    }

    /**
     * @noinspection PhpUnused
     *
     * @param array<array{string, string}> $languageDirections
     */
    public function scopeHasAnyOfLanguageDirections(Builder $builder, array $languageDirections): void
    {
        $builder->where(function (Builder $groupedClause) use ($languageDirections) {
            collect($languageDirections)->eachSpread(
                function (string $sourceLanguageClassifierValueId, string $destinationLanguageClassifierValueId) use ($groupedClause) {
                    $groupedClause->orWhere([
                        'source_language_classifier_value_id' => $sourceLanguageClassifierValueId,
                        'destination_language_classifier_value_id' => $destinationLanguageClassifierValueId,
                    ]);
                });
        });
    }
}
