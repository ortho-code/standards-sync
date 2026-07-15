<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Infrastructure\Filesystem;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystemComponent;

/**
 * Reads an org package's distributed file contents from its templates directory.
 * A rule set pulls content in through here and hands the engine a string, so the pipeline still never walks or copies loose files.
 */
final readonly class TemplateDirectory
{
    private SymfonyFilesystemComponent $filesystem;

    public function __construct(private Path $directory)
    {
        $this->filesystem = new SymfonyFilesystemComponent();
    }

    /** Reads a file relative to the directory; throws (never warns) when it is missing or unreadable. */
    public function read(string $name): string
    {
        return $this->filesystem->readFile($this->directory->join(Path::fromString($name))->value());
    }
}
