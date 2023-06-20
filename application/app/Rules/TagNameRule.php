<?php

namespace App\Rules;

use App\Models\Tag;
use Arr;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class TagNameRule implements ValidationRule
{
    private const REGEX = '/^[\p{L}0-9][\p{L}\-0-9\s]+/u';

    private const MAX_LENGTH = 50;

    public function __construct(private readonly string $institutionId, private readonly ?string $tagType = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $tagName = Arr::get($value, 'name');
        $tagType = $this->tagType ?: Arr::get($value, 'type');

        if (empty($tagName)) {
            $fail('The :attribute is required.');
        }

        if (Str::length($tagName) > self::MAX_LENGTH) {
            $fail('The :attribute length should be less than '.self::MAX_LENGTH.' characters');
        }

        if (! Str::match(self::REGEX, $tagName)) {
            $fail('The :attribute has incorrect characters.');
        }

        $isExists = filled($tagName) && Tag::query()->where('name', 'ilike', $tagName)
            ->where('institution_id', $this->institutionId)
            ->where('type', $tagType)->whereNot('id', Arr::get($value, 'id'))
            ->exists();

        if ($isExists) {
            $fail('Tag with such name already exists');
        }
    }
}
