<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\InstitutionDiscount
 *
 * @property int $id
 * @property string $institution_id
 * @property string|null $discount_percentage_101
 * @property string|null $discount_percentage_repetitions
 * @property string|null $discount_percentage_100
 * @property string|null $discount_percentage_95_99
 * @property string|null $discount_percentage_85_94
 * @property string|null $discount_percentage_75_84
 * @property string|null $discount_percentage_50_74
 * @property string|null $discount_percentage_0_49
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder|InstitutionDiscount newModelQuery()
 * @method static Builder|InstitutionDiscount newQuery()
 * @method static Builder|InstitutionDiscount query()
 * @method static Builder|InstitutionDiscount whereCreatedAt($value)
 * @method static Builder|InstitutionDiscount whereDiscountPercentage049($value)
 * @method static Builder|InstitutionDiscount whereDiscountPercentage100($value)
 * @method static Builder|InstitutionDiscount whereDiscountPercentage101($value)
 * @method static Builder|InstitutionDiscount whereDiscountPercentage5074($value)
 * @method static Builder|InstitutionDiscount whereDiscountPercentage7584($value)
 * @method static Builder|InstitutionDiscount whereDiscountPercentage8594($value)
 * @method static Builder|InstitutionDiscount whereDiscountPercentage9599($value)
 * @method static Builder|InstitutionDiscount whereDiscountPercentageRepetitions($value)
 * @method static Builder|InstitutionDiscount whereId($value)
 * @method static Builder|InstitutionDiscount whereInstitutionId($value)
 * @method static Builder|InstitutionDiscount whereUpdatedAt($value)
 * @mixin Eloquent
 */
class InstitutionDiscount extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

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
}
