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
 * App\Models\CatToolTm
 *
 * @property int $id
 * @property string $sub_project_id
 * @property string $tm_id
 * @property bool $is_writable
 * @property bool $is_readable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $deleted_at
 *
 * @property-read SubProject $subProject
 *
 * @method static Builder|CatToolTm newModelQuery()
 * @method static Builder|CatToolTm newQuery()
 * @method static Builder|CatToolTm query()
 * @method static Builder|CatToolTm whereCreatedAt($value)
 * @method static Builder|CatToolTm whereDeletedAt($value)
 * @method static Builder|CatToolTm whereId($value)
 * @method static Builder|CatToolTm whereIsReadable($value)
 * @method static Builder|CatToolTm whereIsWritable($value)
 * @method static Builder|CatToolTm whereSubProjectId($value)
 * @method static Builder|CatToolTm whereTmId($value)
 * @method static Builder|CatToolTm whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class CatToolTm extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'is_writable' => 'boolean',
        'is_readable' => 'boolean',
    ];

    public function subProject(): BelongsTo
    {
        return $this->belongsTo(SubProject::class);
    }
}
