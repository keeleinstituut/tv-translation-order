<?php

namespace App\Enums;

enum JobKey: string
{
    case JOB_TRANSLATION = 'job_translation';
    case JOB_REVISION = 'job_revision';
    case JOB_OVERVIEW = 'job_overview';
}
