<?php

namespace App\Services\Workflows\Templates;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

abstract class BaseWorkflowTemplate
{
    private const TEMPLATES_PATH = '/Services/Workflows/Templates/Files';

    protected Filesystem $storage;

    public function __construct()
    {
        $this->storage = Storage::build([
            'driver' => 'local',
            'root' => app_path(),
        ]);
    }

    public function getDefinition(): string
    {
        return $this->storage->get(join(DIRECTORY_SEPARATOR, [self::TEMPLATES_PATH, $this->getTemplateFileName()]));
    }

    abstract protected function getTemplateFileName(): string;
}
