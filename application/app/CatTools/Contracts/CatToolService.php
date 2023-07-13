<?php

namespace App\CatTools\Contracts;

use Illuminate\Support\Collection;

interface CatToolService
{
    public function init(Collection $files): void;


}
