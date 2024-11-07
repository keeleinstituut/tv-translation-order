<?php

namespace App\Enums;

enum TranslationMemoryType: string
{
    case Public = 'public';

    case Private = 'private';

    case Unspecified = 'unspecified';
}
