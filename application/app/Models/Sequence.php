<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * App\Models\Sequence
 *
 * @property string|null $id
 * @property string|null $name
 * @property string|null $sequenceable_type
 * @property string|null $sequenceable_id
 * @property int|null $current_value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|Eloquent $sequenceable
 *
 * @method static Builder|Sequence newModelQuery()
 * @method static Builder|Sequence newQuery()
 * @method static Builder|Sequence query()
 * @method static Builder|Sequence whereCreatedAt($value)
 * @method static Builder|Sequence whereCurrentValue($value)
 * @method static Builder|Sequence whereId($value)
 * @method static Builder|Sequence whereName($value)
 * @method static Builder|Sequence whereSequenceableId($value)
 * @method static Builder|Sequence whereSequenceableType($value)
 * @method static Builder|Sequence whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class Sequence extends Model
{
    use HasFactory;
    use HasUuids;

    public const INSTITUTION_PROJECT_SEQ = 'INSTITUTION_PROJECT_SEQUENCE';
    public const PROJECT_SUBPROJECT_SEQ = 'PROJECT_SUBPROJECT_SEQUENCE';

    public function sequenceable()
    {
        return $this->morphTo();
    }

    public function incrementCurrentValue()
    {
        return DB::transaction(function () {
            $value = $this->current_value;
            $this->current_value += 1;
            $this->save();

            return $value;
        });
    }
}
