<?php

namespace App\CatTools;

readonly class SubOrder
{
    public string $id;

    public string $sourceLanguage;

    public string $targetLanguages;

    public function __construct(array $params)
    {
        $params = collect($params);
        $this->id = $params->get('id', 'PPA-2023-07-K-126');
        $this->sourceLanguage = $params->get('source_lang', 'en-US');
        $this->targetLanguages = $params->get('target_lang', 'uk-UA');
    }

    public function markAsFailed(string $message): void
    {
        var_dump("The MateCat processing of the sub-order failed. Reason: $message");
    }

    public function getMeta(): array
    {
        return [
            'id' => $this->id,
            'source_lang' => $this->sourceLanguage,
            'target_lang' => $this->targetLanguages
        ];
    }
}
