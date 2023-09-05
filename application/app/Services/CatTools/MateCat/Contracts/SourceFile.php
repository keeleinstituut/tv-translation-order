<?php

namespace App\Services\CatTools\MateCat\Contracts;

interface SourceFile
{
    public function getName(): string;
    public function getContent(): string;
}
