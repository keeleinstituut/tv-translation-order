<?php

namespace Tests\Feature;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\CachedEntities\InstitutionUser;
use App\Models\Price;
use App\Models\Skill;
use App\Models\Tag;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\MissingValue;
use Illuminate\Support\Arr;

class RepresentationHelpers
{
    public static function createTagFlatRepresentation(Tag $tag): array
    {
        return Arr::only(
            $tag->toArray(),
            ['id', 'institution_id', 'name', 'type', 'created_at', 'updated_at']
        );
    }

    public static function createPriceRepresentation(Price $obj): array
    {
        return self::clean([
            'id' => $obj->id,
            'vendor_id' => $obj->vendor_id,
            'skill_id' => $obj->skill_id,
            'src_lang_classifier_value_id' => $obj->src_lang_classifier_value_id,
            'dst_lang_classifier_value_id' => $obj->dst_lang_classifier_value_id,
            'created_at' => $obj->created_at->toIsoString(),
            'updated_at' => $obj->updated_at->toIsoString(),
            'character_fee' => $obj->character_fee,
            'word_fee' => $obj->word_fee,
            'page_fee' => $obj->page_fee,
            'minute_fee' => $obj->minute_fee,
            'hour_fee' => $obj->hour_fee,
            'minimal_fee' => $obj->minimal_fee,
            'vendor' => self::transformRelation($obj, 'vendor', self::createVendorRepresentation(...)),
            'source_language_classifier_value' => self::transformRelation($obj, 'sourceLanguageClassifierValue', self::createClassifierValueRepresentation(...)),
            'destination_language_classifier_value' => self::transformRelation($obj, 'destinationLanguageClassifierValue', self::createClassifierValueRepresentation(...)),
            'skill' => self::transformRelation($obj, 'skill', self::createSkillRepresentation(...)),
        ]);
    }

    public static function createVendorRepresentation(Vendor $obj): array
    {
        $representation = [
            'id' => $obj->id,
            'institution_user_id' => $obj->institution_user_id,
            'company_name' => $obj->company_name,
            'created_at' => $obj->created_at->toIsoString(),
            'updated_at' => $obj->updated_at->toIsoString(),
            'comment' => $obj->comment,
        ];

        // Only include discount fields if institutionUser.institutionDiscount is loaded
        // This matches VendorResource behavior
        if ($obj->relationLoaded('institutionUser') && $obj->institutionUser?->relationLoaded('institutionDiscount')) {
            $discount = $obj->getVolumeAnalysisDiscount()->jsonSerialize();
            $representation = array_merge($representation, $discount);
        }

        $representation['prices'] = self::transformRelation($obj, 'prices', self::createPriceRepresentation(...));
        $representation['institution_user'] = self::transformRelation($obj, 'institutionUser', self::createInstitutionUserRepresentation(...));
        $representation['tags'] = self::transformRelation($obj, 'tags', self::createTagFlatRepresentation(...));

        return self::clean($representation);
    }

    public static function createClassifierValueRepresentation(ClassifierValue $obj): array
    {
        return self::clean([
            'id' => $obj->id,
            'type' => $obj->type->value,
            'value' => $obj->value,
            'name' => $obj->name,
            'meta' => $obj->meta,
        ]);
    }

    public static function createSkillRepresentation(Skill $obj): array
    {
        return self::clean([
            'id' => $obj->id,
            'name' => $obj->name,
        ]);
    }

    public static function createInstitutionUserRepresentation(InstitutionUser $obj): array
    {
        return self::clean([
            'id' => $obj->id,
            'email' => $obj->email,
            'phone' => $obj->phone,
            'user' => $obj->user,
            'institution' => $obj->institution,
            'department' => $obj->department,
            'roles' => collect($obj->roles)->map(fn ($role) => collect($role)->only('id', 'name')),
            'vacations' => $obj->vacations,
        ]);
    }

    private static function clean($obj): array
    {
        return collect($obj)
            ->reject(fn ($value) => $value instanceof MissingValue)
            ->toArray();
    }

    private static function transformRelation(Model $model, string $relation, callable $callable)
    {
        if (! $model->relationLoaded($relation)) {
            return new MissingValue;
        }

        $relationValue = $model->getRelation($relation);

        if ($relationValue instanceof Model) {
            return $callable($relationValue);
        } else {
            return collect($relationValue)->map($callable)->toArray();
        }
    }
}
