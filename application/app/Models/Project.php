<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Services\Prices\PriceCalculator;
use App\Services\Prices\ProjectPriceCalculator;
use App\Services\Workflows\ProjectWorkflowProcessInstance;
use Database\Factories\ProjectFactory;
use Eloquent;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Throwable;

/**
 * App\Models\Project
 *
 * @property string|null $id
 * @property string|null $ext_id
 * @property string|null $reference_number
 * @property string|null $institution_id
 * @property string|null $type_classifier_value_id
 * @property string|null $comments
 * @property string|null $workflow_template_id
 * @property string|null $workflow_instance_ref
 * @property float|null $price
 * @property Carbon|null $deadline_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $event_start_at
 * @property Carbon|null $deleted_at
 * @property string|null $manager_institution_user_id
 * @property string|null $client_institution_user_id
 * @property string|null $translation_domain_classifier_value_id
 * @property ProjectStatus $status
 * @property-read Institution|null $institution
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Sequence|null $subProjectSequence
 * @property-read Collection<int, SubProject> $subProjects
 * @property-read int|null $sub_projects_count
 * @property-read Collection<int, ClassifierValue> $translationDomainClassifierValues
 * @property-read int|null $translation_domain_classifier_values_count
 * @property-read Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read ClassifierValue|null $typeClassifierValue
 * @property-read ClassifierValue|null $translationDomainClassifierValue
 * @property-read InstitutionUser|null $clientInstitutionUser
 * @property-read InstitutionUser|null $managerInstitutionUser
 *
 * @method static ProjectFactory factory($count = null, $state = [])
 * @method static Builder|Project newModelQuery()
 * @method static Builder|Project newQuery()
 * @method static Builder|Project query()
 * @method static Builder|Project whereClientInstitutionUserId($value)
 * @method static Builder|Project whereComments($value)
 * @method static Builder|Project whereCreatedAt($value)
 * @method static Builder|Project whereDeadlineAt($value)
 * @method static Builder|Project whereDeletedAt($value)
 * @method static Builder|Project whereEventStartDatetime($value)
 * @method static Builder|Project whereExtId($value)
 * @method static Builder|Project whereId($value)
 * @method static Builder|Project whereInstitutionId($value)
 * @method static Builder|Project whereManagerInstitutionUserId($value)
 * @method static Builder|Project whereReferenceNumber($value)
 * @method static Builder|Project whereTypeClassifierValueId($value)
 * @method static Builder|Project whereTranslationDomainClassifierValueId($value)
 * @method static Builder|Project whereUpdatedAt($value)
 * @method static Builder|Project whereWorkflowInstanceRef($value)
 * @method static Builder|Project whereWorkflowTemplateId($value)
 * @method static Builder|Project whereStatus($value)
 * @method static Builder|Project onlyTrashed()
 * @method static Builder|Project whereEventStartAt($value)
 * @method static Builder|Project withTrashed()
 * @method static Builder|Project withoutTrashed()
 * @method static Builder|Project hasAnyOfLanguageDirections(array[] $languageDirections)
 *
 * @mixin Eloquent
 */
class Project extends Model implements HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'projects';

    public const SOURCE_FILES_COLLECTION = 'source';

    public const HELP_FILES_COLLECTION = 'help';

    public const FINAL_FILES_COLLECTION = 'final';

    public const INTERMEDIATE_FILES_COLLECTION_PREFIX = 'intermediate';

    public const REVIEW_FILES_COLLECTION_PREFIX = 'review';

    public const HELP_FILE_TYPES = [
        'STYLE_GUIDE',
        'TERM_BASE',
        'REFERENCE_FILE',
    ];

    protected $guarded = [];

    protected $casts = [
        'event_start_at' => 'datetime',
        'deadline_at' => 'datetime',
        'price' => 'float',
        'status' => ProjectStatus::class,
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function subProjectSequence(): Builder
    {
        return $this->morphOne(Sequence::class, 'sequenceable')
            ->where('name', Sequence::PROJECT_SUBPROJECT_SEQ);
    }

    public function typeClassifierValue(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'type_classifier_value_id');
    }

    public function translationDomainClassifierValue(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'translation_domain_classifier_value_id');
    }

    public function subProjects(): HasMany
    {
        return $this->hasMany(SubProject::class);
    }

    public function sourceFiles()
    {
        return $this->media()->where('collection_name', self::SOURCE_FILES_COLLECTION);
    }

    public function helpFiles()
    {
        return $this->media()->where('collection_name', self::HELP_FILES_COLLECTION);
    }

    public function finalFiles()
    {
        return $this->media()->where('collection_name', self::FINAL_FILES_COLLECTION);
    }

    public function reviewFiles()
    {
        return $this->media()->where('collection_name', 'like', self::REVIEW_FILES_COLLECTION_PREFIX . '%');
    }

    public function managerInstitutionUser(): BelongsTo
    {
        return $this->belongsTo(InstitutionUser::class, 'manager_institution_user_id');
    }

    public function clientInstitutionUser(): BelongsTo
    {
        return $this->belongsTo(InstitutionUser::class, 'client_institution_user_id');
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->using(Taggable::class);
    }

    public function workflow(): ProjectWorkflowProcessInstance
    {
        return new ProjectWorkflowProcessInstance($this);
    }

    /** @throws Throwable */
    public function initSubProjects(ClassifierValue $sourceLanguage, \Illuminate\Support\Collection $destinationLanguages, $reinitialize = false): array
    {
        $makeSubProject = function ($destinationLanguage) use ($sourceLanguage) {
            $subProject = new SubProject();
            $subProject->project_id = $this->id;
            $subProject->file_collection = self::INTERMEDIATE_FILES_COLLECTION_PREFIX."/$sourceLanguage->value/$destinationLanguage->value";
            $subProject->file_collection_final = self::FINAL_FILES_COLLECTION."/$sourceLanguage->value/$destinationLanguage->value";
            $subProject->source_language_classifier_value_id = $sourceLanguage->id;
            $subProject->destination_language_classifier_value_id = $destinationLanguage->id;
            $subProject->deadline_at = $this->deadline_at;
            return $subProject;
        };

        $existingSubProjects = $this->subProjects;
        $requestedSubProjects = collect($destinationLanguages)->map($makeSubProject(...));

        $comparator = function (\Illuminate\Support\Collection $others) use ($reinitialize) {
            $equals = function (SubProject $that, SubProject $other) use ($reinitialize) {
                if ($reinitialize) {
                    return false;
                }

                return $that->source_language_classifier_value_id == $other->source_language_classifier_value_id
                    && $that->destination_language_classifier_value_id == $other->destination_language_classifier_value_id;
            };

            return function (SubProject $that) use ($others, $equals) {
                return $others->contains(fn ($other) => $equals($that, $other));
            };
        };

        $toCreate = $requestedSubProjects->reject($comparator($existingSubProjects));
        $toDelete = $existingSubProjects->reject($comparator($requestedSubProjects));

        collect($toDelete)->each(function (SubProject $subProject) {
            $subProject->delete();
        });

        collect($toCreate)->each(function (SubProject $subProject) {
            $subProject->saveOrFail();

            $this->getMedia('source')->each(function ($sourceFile) use ($subProject) {
                /** @var Media $sourceFile */
                $copiedFile = $sourceFile->copy($this, $subProject->file_collection);
                $sourceFile->copies()->save($copiedFile);
            });

            $subProject->initAssignments();
        });

        return [$toCreate->count(), $toDelete->count()];
    }

    /**
     * @noinspection PhpUnused
     *
     * @param  array<array{string, string}>  $languageDirections
     */
    public function scopeHasAnyOfLanguageDirections(Builder $builder, array $languageDirections): void
    {
        $builder->where(function (Builder $groupedClause) use ($languageDirections) {
            collect($languageDirections)->eachSpread(
                function (string $sourceLanguageClassifierValueId, string $destinationLanguageClassifierValueId) use ($groupedClause) {
                    $groupedClause->orWhereRelation('subProjects', [
                        'source_language_classifier_value_id' => $sourceLanguageClassifierValueId,
                        'destination_language_classifier_value_id' => $destinationLanguageClassifierValueId,
                    ]);
                }
            );
        });
    }

    public function getPriceCalculator(): PriceCalculator
    {
        return new ProjectPriceCalculator($this);
    }
}
