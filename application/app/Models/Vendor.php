<?php

namespace App\Models;

use App\Models\CachedEntities\InstitutionUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $connection = 'pgsql_app';

    protected $table = 'vendors';

    protected $fillable = [
        'institution_user_id',
        'company_name',
        'discount_percentage_101',
        'discount_percentage_repetitions',
        'discount_percentage_100',
        'discount_percentage_95_99',
        'discount_percentage_85_94',
        'discount_percentage_75_84',
        'discount_percentage_50_74',
        'discount_percentage_0_49',
    ];

    protected $casts = [
        'discount_percentage_101' => 'float',
        'discount_percentage_repetitions' => 'float',
        'discount_percentage_100' => 'float',
        'discount_percentage_95_99' => 'float',
        'discount_percentage_85_94' => 'float',
        'discount_percentage_75_84' => 'float',
        'discount_percentage_50_74' => 'float',
        'discount_percentage_0_49' => 'float',
    ];

    public function institutionUser(): BelongsTo
    {
        return $this->belongsTo(InstitutionUser::class);
    }

    public function prices()
    {
        return $this->hasMany(Price::class);
    }

    public function tags()
    {
        return $this
            ->morphToMany(Tag::class, 'taggable')
            ->using(Taggable::class);
    }
}
