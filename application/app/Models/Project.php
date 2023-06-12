<?php

namespace App\Models;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use App\Services\WorkflowProcessInstanceService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    public const SOURCE_FILES_COLLECTION = 'source';
    public const HELP_FILES_COLLECTION = 'help';
    public const FINAL_FILES_COLLECTION = 'final';
    public const INTERMEDIATE_FILES_COLLECTION_PREFIX = 'intermediate';

    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function subProjectSequence()
    {
        return $this->morphOne(Sequence::class, 'sequenceable')
            ->where('name', Sequence::PROJECT_SUBPROJECT_SEQ);
    }

    public function typeClassifierValue()
    {
        return $this->belongsTo(ClassifierValue::class, 'type_classifier_value_id');
    }

    public function subProjects()
    {
        return $this->hasMany(SubProject::class);
    }

    public function workflow()
    {
        return new WorkflowProcessInstanceService($this);
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

    public function initSubProjects($sourceLanguage, $destinationLanguages): void
    {
        collect($destinationLanguages)->each(function ($destinationLanguage) use ($sourceLanguage) {
            $subProject = new SubProject();
            $subProject->project_id = $this->id;
            $subProject->file_collection = self::INTERMEDIATE_FILES_COLLECTION_PREFIX . "/$sourceLanguage->value/$destinationLanguage->value";
            $subProject->file_collection_final = self::FINAL_FILES_COLLECTION . "/$sourceLanguage->value/$destinationLanguage->value";
            $subProject->source_language_classifier_value_id = $sourceLanguage->id;
            $subProject->destination_language_classifier_value_id = $destinationLanguage->id;
            $subProject->save();

            $this->getMedia('source')->each(function ($sourceFile) use ($subProject) {
                $sourceFile->copy($this, $subProject->file_collection);
            });

            $subProject->initAssignments();
        });
    }
}
