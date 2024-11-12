<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\CatToolTmKey
 *
 * @property int $id
 * @property string $sub_project_id
 * @property string $key
 * @property bool $is_writable
 * @property bool $created_as_empty
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read SubProject $subProject
 *
 * @method static Builder|CatToolTmKey newModelQuery()
 * @method static Builder|CatToolTmKey newQuery()
 * @method static Builder|CatToolTmKey query()
 * @method static Builder|CatToolTmKey whereCreatedAt($value)
 * @method static Builder|CatToolTmKey whereDeletedAt($value)
 * @method static Builder|CatToolTmKey whereId($value)
 * @method static Builder|CatToolTmKey whereIsReadable($value)
 * @method static Builder|CatToolTmKey whereIsWritable($value)
 * @method static Builder|CatToolTmKey whereSubProjectId($value)
 * @method static Builder|CatToolTmKey whereTmId($value)
 * @method static Builder|CatToolTmKey whereUpdatedAt($value)
 * @method static Builder|CatToolTmKey whereKey($value)
 *
 * @mixin Eloquent
 */
class CatToolTmKey extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'is_writable' => 'boolean',
        'created_as_empty' => 'boolean'
    ];

    public function subProject(): BelongsTo
    {
        return $this->belongsTo(SubProject::class);
    }
}
