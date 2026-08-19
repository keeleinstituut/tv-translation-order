<?php

namespace App\Models;

use App\Models\CachedEntities\InstitutionUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $institution_user_id
 * @property string $institution_main_language_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InstitutionUser $institutionUser
 * @property-read \App\Models\InstitutionMainLanguage $mainLanguage
 * @method static Builder<static>|InstitutionUserPinnedLanguage newModelQuery()
 * @method static Builder<static>|InstitutionUserPinnedLanguage newQuery()
 * @method static Builder<static>|InstitutionUserPinnedLanguage query()
 * @method static Builder<static>|InstitutionUserPinnedLanguage whereCreatedAt($value)
 * @method static Builder<static>|InstitutionUserPinnedLanguage whereId($value)
 * @method static Builder<static>|InstitutionUserPinnedLanguage whereInstitutionMainLanguageId($value)
 * @method static Builder<static>|InstitutionUserPinnedLanguage whereInstitutionUserId($value)
 * @method static Builder<static>|InstitutionUserPinnedLanguage whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class InstitutionUserPinnedLanguage extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'institution_user_pinned_languages';

    protected $guarded = [];

    public function institutionUser(): BelongsTo
    {
        return $this->belongsTo(InstitutionUser::class);
    }

    public function mainLanguage(): BelongsTo
    {
        return $this->belongsTo(InstitutionMainLanguage::class, 'institution_main_language_id');
    }
}
