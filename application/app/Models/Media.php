<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;
use Throwable;

/**
 * App\Models\Media
 *
 * @property int|null $id
 * @property string|null $model_type
 * @property string|null $model_id
 * @property string|null $uuid
 * @property string|null $collection_name
 * @property string|null $name
 * @property string|null $file_name
 * @property string|null $mime_type
 * @property string|null $disk
 * @property string|null $conversions_disk
 * @property int|null $size
 * @property array|null $manipulations
 * @property array|null $custom_properties
 * @property array|null $generated_conversions
 * @property array|null $responsive_images
 * @property int|null $order_column
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $model
 *
 * @method static MediaCollection<int, static> all($columns = ['*'])
 * @method static MediaCollection<int, static> get($columns = ['*'])
 * @method static Builder|Media newModelQuery()
 * @method static Builder|Media newQuery()
 * @method static Builder|Media ordered()
 * @method static Builder|Media query()
 * @method static Builder|Media whereCollectionName($value)
 * @method static Builder|Media whereConversionsDisk($value)
 * @method static Builder|Media whereCreatedAt($value)
 * @method static Builder|Media whereCustomProperties($value)
 * @method static Builder|Media whereDisk($value)
 * @method static Builder|Media whereFileName($value)
 * @method static Builder|Media whereGeneratedConversions($value)
 * @method static Builder|Media whereId($value)
 * @method static Builder|Media whereManipulations($value)
 * @method static Builder|Media whereMimeType($value)
 * @method static Builder|Media whereModelId($value)
 * @method static Builder|Media whereModelType($value)
 * @method static Builder|Media whereName($value)
 * @method static Builder|Media whereOrderColumn($value)
 * @method static Builder|Media whereResponsiveImages($value)
 * @method static Builder|Media whereSize($value)
 * @method static Builder|Media whereUpdatedAt($value)
 * @method static Builder|Media whereUuid($value)
 *
 * @mixin Eloquent
 */
class Media extends BaseMedia
{

    /**
     * @throws Throwable
     */
    public function moveToProjectFinalFile(SubProject $subProject): void
    {
        // To prevent coping of the custom properties we will forget them.
        if ($this->hasCustomProperty('copy_media_id')) {
            $this->forgetCustomProperty('copy_media_id');
        }

        if ($this->hasCustomProperty('is_project_final_file')) {
            $this->forgetCustomProperty('is_project_final_file');
        }

        /** @var Media $projectFinalFile */
        $projectFinalFile = $this->copy($subProject->project, Project::FINAL_FILES_COLLECTION);

        $projectFinalFile->setCustomProperty('source_media_id', $this->id);
        $projectFinalFile->setCustomProperty('sub_project_id', $subProject->id);
        $projectFinalFile->saveOrFail();

        $this->setCustomProperty('copy_media_id', $projectFinalFile->id);
        $this->setCustomProperty('is_project_final_file', true);
        $this->saveOrFail();
    }
}
