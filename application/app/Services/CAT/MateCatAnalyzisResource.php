<?php

namespace App\Services\CAT;

use Illuminate\Http\Resources\Json\JsonResource;

class MateCatAnalyzisResource extends JsonResource
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
            'chunk_id' => $this['chunk_id'],
            '101' => $this->discount_percentage_101,
            'repetitions' => $this->discount_percentage_repetitions,
            '100' => $this->discount_percentage_100,
            '95_99' => $this->discount_percentage_95_99,
            '85_94' => $this->discount_percentage_85_94,
            '75_84' => $this->discount_percentage_75_84,
            '50_74' => $this->discount_percentage_50_74,
            '0_49' => $this->discount_percentage_0_49,
        ];
    }
}
