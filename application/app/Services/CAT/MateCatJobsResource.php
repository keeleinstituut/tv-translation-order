<?php

namespace App\Services\CAT;

use Illuminate\Http\Resources\Json\JsonResource;

class MateCatJobsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        // return parent::toArray($request);
        return [
            'target_language' => $this['job']['target_lang'],
            'original_download_url' => $this['job']['original_download_url'],
            'translation_download_url' => $this['job']['translation_download_url'],
            'xliff_download_url' => $this['job']['xliff_download_url'],
//            'password' => $this['password'],
            'translate_url' => $this['translate_url'],
            'revisions' => $this['revise_urls'],
        ];
    }
}
