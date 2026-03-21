<?php

namespace App\Models;

use App\Enums\SkillCode;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\Skill
 *
 * @property string|null $id
 * @property string|null $name
 * @property string|null $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder|Skill newModelQuery()
 * @method static Builder|Skill newQuery()
 * @method static Builder|Skill query()
 * @method static Builder|Skill whereCode($value)
 * @method static Builder|Skill whereCreatedAt($value)
 * @method static Builder|Skill whereId($value)
 * @method static Builder|Skill whereName($value)
 * @method static Builder|Skill whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class Skill extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'skills';

    public static function findByCode(SkillCode $code): ?static
    {
        return static::query()->where('code', $code)->first();
    }
}
