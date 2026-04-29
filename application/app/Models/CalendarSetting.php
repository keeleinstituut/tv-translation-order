<?php

namespace App\Models;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_id
 * @property int $reaction_time_minutes
 * @property int $buffer_before_minutes
 * @property int $buffer_after_minutes
 * @property string|null $default_project_type_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ClassifierValue $defaultProjectType
 * @property-read Institution $institution
 * @method static Builder<static>|CalendarSetting newModelQuery()
 * @method static Builder<static>|CalendarSetting newQuery()
 * @method static Builder<static>|CalendarSetting query()
 * @method static Builder<static>|CalendarSetting whereCreatedAt($value)
 * @method static Builder<static>|CalendarSetting whereDefaultProjectTypeId($value)
 * @method static Builder<static>|CalendarSetting whereId($value)
 * @method static Builder<static>|CalendarSetting whereInstitutionId($value)
 * @method static Builder<static>|CalendarSetting whereReactionTimeMinutes($value)
 * @method static Builder<static>|CalendarSetting whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CalendarSetting extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'calendar_settings';

    protected $guarded = [];

    protected $casts = [
        'reaction_time_minutes' => 'integer',
        'buffer_before_minutes' => 'integer',
        'buffer_after_minutes' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function defaultProjectType(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class);
    }
}
