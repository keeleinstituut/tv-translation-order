<?php

namespace App\Models;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Models\CachedEntities\InstitutionUser;
use App\Services\WorkflowProcessInstanceService;
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
 * @property Carbon|null $deadline_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $event_start_at
 * @property Carbon|null $deleted_at
 * @property string|null $manager_institution_user_id
 * @property string|null $client_institution_user_id
 * @property string|null $translation_domain_classifier_value_id
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
    public const SOURCE_FILES_COLLECTION = 'source';

    protected $guarded = [];

    protected $table = 'projects';

    public const HELP_FILES_COLLECTION = 'help';

    public const HELP_FILE_TYPES = [
        'STYLE_GUIDE',
        'TERM_BASE',
        'REFERENCE_FILE',
    ];

    public const FINAL_FILES_COLLECTION = 'final';

    public const INTERMEDIATE_FILES_COLLECTION_PREFIX = 'intermediate';

    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $casts = [
        'event_start_at' => 'datetime',
        'deadline_at' => 'datetime',
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

    public function workflow(): WorkflowProcessInstanceService
    {
        return new WorkflowProcessInstanceService($this);
    }

    public function getSourceFiles(): MediaCollection
    {
        return $this->media->filter(fn (Media $media) => $media->collection_name === self::SOURCE_FILES_COLLECTION);
    }

    public function getHelpFiles(): MediaCollection
    {
        return $this->media->filter(fn (Media $media) => $media->collection_name === self::HELP_FILES_COLLECTION);
    }

    public function getFinalFiles(): MediaCollection
    {
        return $this->media->filter(fn (Media $media) => $media->collection_name === self::FINAL_FILES_COLLECTION);
    }

    /** @throws Throwable */
    public function initSubProjects(ClassifierValue $sourceLanguage, \Illuminate\Support\Collection $destinationLanguages): void
    {
        collect($destinationLanguages)->each(function ($destinationLanguage) use ($sourceLanguage) {
            $subProject = new SubProject();
            $subProject->project_id = $this->id;
            $subProject->file_collection = self::INTERMEDIATE_FILES_COLLECTION_PREFIX."/$sourceLanguage->value/$destinationLanguage->value";
            $subProject->file_collection_final = self::FINAL_FILES_COLLECTION."/$sourceLanguage->value/$destinationLanguage->value";
            $subProject->source_language_classifier_value_id = $sourceLanguage->id;
            $subProject->destination_language_classifier_value_id = $destinationLanguage->id;
            $subProject->saveOrFail();

            $this->getMedia('source')->each(function ($sourceFile) use ($subProject) {
                $sourceFile->copy($this, $subProject->file_collection);
            });

            $subProject->initAssignments();
        });
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

    public function getSourceLanguageClassifierValue(): ?ClassifierValue
    {
        return $this->subProjects->first()?->sourceLanguageClassifierValue;
    }

    public function getDestinationLanguageClassifierValues(): Collection
    {
        return $this->subProjects->map(fn (SubProject $subProject) => $subProject->destinationLanguageClassifierValue);
    }

    public function computeStatus(): null
    {
        // TODO: Compute status of project (derived from workflow/subproject/task data)
        return null;
    }

    public function computeCost(): null
    {
        // TODO: Compute cost of project (derived from workflow/subproject/task data)
        return null;
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
}
