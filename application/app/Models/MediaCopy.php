<?php

namespace App\Models;

use App\Observers\MediaObserver;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * The table is needed to store relations between files as one file can be copied to different collections.
 *
 * Example: We store source files inside the Project source files collection and SubProjects source files collection.
 * Use case: When project source file was removed we have to remove the file from all subprojects source files.
 *
 * @see Project::initSubProjects()
 * @see SubProject::syncFinalFilesWithProject()
 * @see MediaObserver
 *
 * App\Models\MediaCopy
 *
 * @property string $source_media_id
 * @property string $copy_media_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder|MediaCopy newModelQuery()
 * @method static Builder|MediaCopy newQuery()
 * @method static Builder|MediaCopy query()
 * @method static Builder|MediaCopy whereCopyMediaId($value)
 * @method static Builder|MediaCopy whereCreatedAt($value)
 * @method static Builder|MediaCopy whereSourceMediaId($value)
 * @method static Builder|MediaCopy whereUpdatedAt($value)
 * @method static Builder|MediaCopy whereUuid($value)
 *
 * @mixin Eloquent
 */
class MediaCopy extends Pivot
{
    use HasFactory, HasUuids;

    protected $table = 'media_copies';

    public function copy(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'copy_media_id', 'uuid');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'source_media_id', 'uuid');
    }
}
