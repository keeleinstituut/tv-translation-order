<?php

namespace App\Rules;

use App\Models\Tag;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

class TagNameRule implements ValidationRule
{
    private const REGEX = '/^[\p{L}0-9][\p{L}\-0-9\s]+/u';

    private const MAX_LENGTH = 50;

    private string $ignoreId = '';

    public function __construct(private readonly string $institutionId, private readonly ?string $tagType)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Str::length($value) > self::MAX_LENGTH) {
            $fail('The :attribute length should be less than '.self::MAX_LENGTH.' characters');
        }

        if (! Str::match(self::REGEX, $value)) {
            $fail('The :attribute has incorrect characters.');
        }

        $isExists = Tag::query()->where($attribute, 'ilike', $value)
            ->where('institution_id', $this->institutionId)
            ->where('type', $this->tagType)
            ->when($this->ignoreId, fn (Builder $query, string $id) => $query->whereNot('id', $id))
            ->exists();

        if ($isExists) {
            $fail('Tag with such name already exists');
        }
    }

    public function ignore(string $id)
    {
        $this->ignoreId = $id;
    }
}
