<?php

namespace App\CatTools\MateCat\Contracts;

interface SourceFile
{
    public function getName(): string;
    public function getContent(): string;
}
