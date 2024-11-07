<?php

namespace App\Models;

use App\Models\CachedEntities\InstitutionUser;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

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
 * @property string|null $assignment_id
 * @property string|null $institution_user_id
 *
 * @property-read Model|Eloquent $model
 *
 * @property MediaCollection<int, static> $copies
 * @property MediaCollection<int, static> $sources
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
 * @method static MediaCollection<int, static> all($columns = ['*'])
 * @method static MediaCollection<int, static> get($columns = ['*'])
 * @method static MediaCollection<int, static> all($columns = ['*'])
 * @method static MediaCollection<int, static> get($columns = ['*'])
 *
 * @mixin Eloquent
 */
class Media extends BaseMedia
{
    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class, 'id', 'assignment_id');
    }

    public function institutionUser(): HasOne
    {
        return $this->hasOne(InstitutionUser::class, 'id', 'institution_user_id');
    }

    public function getAssignmentIdAttribute()
    {
        return data_get($this->custom_properties, 'assignment_id');
    }

    public function getInstitutionUserIdAttribute()
    {
        return data_get($this->custom_properties, 'institution_user_id');
    }

    public function isProjectFinalFile(): bool
    {
        return $this->collection_name === Project::FINAL_FILES_COLLECTION;
    }

    public function copies(): BelongsToMany
    {
        return $this->belongsToMany(
            Media::class,
            'media_copies',
            'source_media_id',
            'copy_media_id',
            'uuid',
            'uuid'
        )->withTimestamps();
    }

    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            Media::class,
            'media_copies',
            'copy_media_id',
            'source_media_id',
            'uuid',
            'uuid'
        )->withTimestamps();
    }
}
