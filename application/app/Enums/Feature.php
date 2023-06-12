<?php

namespace App\Enums;

enum Feature: string
{
    case JOB_TRANSLATION = "job_translation";
    case JOB_REVISION = "job_revision";
    case JOB_OVERVIEW = "job_overview";

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
