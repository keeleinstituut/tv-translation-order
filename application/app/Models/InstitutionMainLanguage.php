<?php

namespace App\Models;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_id
 * @property string $language_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Institution $institution
 * @property-read ClassifierValue $language
 * @property-read Collection<int, \App\Models\InstitutionUserPinnedLanguage> $pinnedByUsers
 * @property-read int|null $pinned_by_users_count
 * @method static Builder<static>|InstitutionMainLanguage newModelQuery()
 * @method static Builder<static>|InstitutionMainLanguage newQuery()
 * @method static Builder<static>|InstitutionMainLanguage query()
 * @method static Builder<static>|InstitutionMainLanguage whereCreatedAt($value)
 * @method static Builder<static>|InstitutionMainLanguage whereId($value)
 * @method static Builder<static>|InstitutionMainLanguage whereInstitutionId($value)
 * @method static Builder<static>|InstitutionMainLanguage whereLanguageId($value)
 * @method static Builder<static>|InstitutionMainLanguage whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class InstitutionMainLanguage extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'institution_main_languages';

    protected $guarded = [];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(ClassifierValue::class, 'language_id');
    }

    public function pinnedByUsers(): HasMany
    {
        return $this->hasMany(InstitutionUserPinnedLanguage::class);
    }
}
