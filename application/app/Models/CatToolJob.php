<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\CatToolJob
 *
 * @property int $id
 * @property string $sub_project_id
 * @property string $ext_id
 * @property string $name
 * @property string $translate_url
 * @property string $progress_percentage
 * @property mixed $volume_analysis
 * @property mixed $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read SubProject|null $subProject
 *
 * @method static Builder|CatToolJob newModelQuery()
 * @method static Builder|CatToolJob newQuery()
 * @method static Builder|CatToolJob query()
 * @method static Builder|CatToolJob whereCreatedAt($value)
 * @method static Builder|CatToolJob whereExtId($value)
 * @method static Builder|CatToolJob whereId($value)
 * @method static Builder|CatToolJob whereMeta($value)
 * @method static Builder|CatToolJob whereProgressPercentage($value)
 * @method static Builder|CatToolJob whereSubProjectId($value)
 * @method static Builder|CatToolJob whereTranslateUrl($value)
 * @method static Builder|CatToolJob whereUpdatedAt($value)
 * @method static Builder|CatToolJob whereVolumeAnalysis($value)
 * @mixin Eloquent
 */
class CatToolJob extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'meta' => AsArrayObject::class,
        'volume_analysis' => AsArrayObject::class,
    ];

    public function subProject()
    {
        return $this->belongsTo(SubProject::class);
    }
}
