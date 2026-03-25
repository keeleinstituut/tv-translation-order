<?php

namespace App\Enums;

enum SkillCode: string
{
    case OralInterpretation = 'ORAL_INTERPRETATION';
    case SimultaneousInterpretation = 'SIMULTANEOUS_INTERPRETATION';
    case ConsecutiveInterpretation = 'CONSECUTIVE_INTERPRETATION';
    case SignLanguage = 'SIGN_LANGUAGE';
    case RecordingTranslation = 'RECORDING_TRANSLATION';
    case Translation = 'TRANSLATION';
    case Editing = 'EDITING';
    case TranslationAndEditing = 'TRANSLATION_AND_EDITING';
    case HandwrittenTranslation = 'HANDWRITTEN_TRANSLATION';
    case InformationExchange = 'INFORMATION_EXCHANGE';
    case TerminologyWork = 'TERMINOLOGY_WORK';
    case SwornTranslation = 'SWORN_TRANSLATION';
}
