<?php

namespace App\Services\Workflows;

use App\Models\CachedEntities\ClassifierValue;
use App\Services\Workflows\Templates\EditAndReviewSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\EditedTranslationCATReviewSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\EditedTranslationCATSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\EditSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\InterpretationSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\ManuscriptTranslationWithReviewSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\ManuscriptTranslationSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\PostTranslationSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\SignLanguageSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\SimultaneousInterpretationSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\SwornTranslationCATWithReviewSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\SwornTranslationCATSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\SwornTranslationWithReviewSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\SwornTranslationSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\TerminologyWithReviewSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\TerminologySubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\TranslationCATWithEditAndReviewSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\TranslationCATWithEditSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\TranslationCATWithReviewSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\TranslationCATSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\TranslationWithEditAndReviewSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\TranslationWithEditSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\TranslationWithReviewSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\TranslationSubProjectWorkflowTemplate;
use App\Services\Workflows\Templates\SubProjectWorkflowTemplateInterface;
use RuntimeException;

class SubProjectWorkflowTemplatePicker
{
    public static function getWorkflowTemplate($workflowTemplateId): SubProjectWorkflowTemplateInterface
    {
        foreach (self::getTemplates() as $workflowTemplate) {
            if ($workflowTemplate->getId() === $workflowTemplateId) {
                return $workflowTemplate;
            }
        }

        throw new RuntimeException("Workflow template not found for $workflowTemplateId");
    }

    public static function getWorkflowTemplateIds(): array
    {
        return collect(self::getTemplates())->map(
            fn (SubProjectWorkflowTemplateInterface $workflowTemplate) => $workflowTemplate->getId()
        )->toArray();
    }

    /**
     * @return SubProjectWorkflowTemplateInterface[]
     */
    public static function getTemplates(): array
    {
        return [
            new EditAndReviewSubProjectWorkflowTemplate,
            new EditedTranslationCATReviewSubProjectWorkflowTemplate,
            new EditedTranslationCATSubProjectWorkflowTemplate,
            new EditSubProjectWorkflowTemplate,
            new InterpretationSubProjectWorkflowTemplate,
            new ManuscriptTranslationWithReviewSubProjectWorkflowTemplate,
            new ManuscriptTranslationSubProjectWorkflowTemplate,
            new PostTranslationSubProjectWorkflowTemplate,
            new SignLanguageSubProjectWorkflowTemplate,
            new SimultaneousInterpretationSubProjectWorkflowTemplate,
            new SwornTranslationCATWithReviewSubProjectWorkflowTemplate,
            new SwornTranslationCATSubProjectWorkflowTemplate,
            new SwornTranslationWithReviewSubProjectWorkflowTemplate,
            new SwornTranslationSubProjectWorkflowTemplate,
            new TerminologyWithReviewSubProjectWorkflowTemplate,
            new TerminologySubProjectWorkflowTemplate,
            new TranslationCATWithEditAndReviewSubProjectWorkflowTemplate,
            new TranslationCATWithEditSubProjectWorkflowTemplate,
            new TranslationCATWithReviewSubProjectWorkflowTemplate,
            new TranslationCATSubProjectWorkflowTemplate,
            new TranslationWithEditAndReviewSubProjectWorkflowTemplate,
            new TranslationWithEditSubProjectWorkflowTemplate,
            new TranslationWithReviewSubProjectWorkflowTemplate,
            new TranslationSubProjectWorkflowTemplate,
        ];
    }
}
