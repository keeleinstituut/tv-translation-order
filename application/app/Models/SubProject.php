<?php

namespace App\Models;

use App\Models\CachedEntities\ClassifierValue;
use App\Services\CatPickerService;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Staudenmeir\EloquentHasManyDeep\Eloquent\CompositeKey;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class SubProject extends Model
{
    use HasFactory;
    use HasUuids;
    use HasRelationships;

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

    public function initAssignments()
    {
        collect($this->project->typeClassifierValue->projectTypeConfig->features)
            ->filter(fn ($elem) => Str::startsWith($elem, 'job'))
            ->each(function ($feature) {
                $assignment = new Assignment();
                $assignment->sub_project_id = $this->id;
                $assignment->feature = $feature;
                $assignment->save();
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
