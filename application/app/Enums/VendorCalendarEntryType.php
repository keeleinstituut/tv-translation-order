<?php

namespace App\Enums;

enum VendorCalendarEntryType: string
{
    case Assignment = 'assignment';
    case ExternalCalendar = 'external_calendar';
    case Vacation = 'vacation';
    case Prebook = 'prebook';
    case Absence = 'absence';
}
