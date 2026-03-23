<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatV2TranslationMemoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sourceLocale = data_get($this, 'source_locale');
        $targetLocale = data_get($this, 'target_locale');

        return [
            'id' => data_get($this, 'id'),
            'name' => data_get($this, 'name'),
            'lang_pair' => $sourceLocale . '_' . $targetLocale,
            'created_at' => data_get($this, 'created_at'),
            'institution_id' => data_get($this, 'meta.institution_id'),
            'type' => data_get($this, 'meta.visibility'),
            'tv_domain' => data_get($this, 'meta.tv_domain'),
            'tv_tags' => data_get($this, 'meta.tv_tags'),
            'comment' => data_get($this, 'meta.tv_comment'),
        ];
    }
}
