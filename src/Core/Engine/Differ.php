<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Engine;

use AlleKnalle\StandardsSync\Core\Filesystem\Filesystem;
use AlleKnalle\StandardsSync\Core\Plan\Change;
use AlleKnalle\StandardsSync\Core\Plan\ChangeKind;
use AlleKnalle\StandardsSync\Core\Plan\DesiredFile;
use AlleKnalle\StandardsSync\Core\Render\FileRenderer;
use RuntimeException;

/** Diffs a desired file against disk, picking the first renderer that supports it. */
final readonly class Differ
{
    /** @param non-empty-list<FileRenderer> $renderers */
    public function __construct(
        private Filesystem $filesystem,
        private array $renderers,
    ) {
    }

    public function diff(DesiredFile $file): Change
    {
        $current = $this->filesystem->read($file->path());
        $desired = $this->rendererFor($file)->render($file, $current);

        $kind = match (true) {
            $current === null => ChangeKind::Create,
            $current === $desired => ChangeKind::InSync,
            default => ChangeKind::Update,
        };

        return new Change($file->path(), $kind, $current, $desired);
    }

    private function rendererFor(DesiredFile $file): FileRenderer
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($file)) {
                return $renderer;
            }
        }

        throw new RuntimeException(sprintf('No renderer supports "%s".', $file->path()->value()));
    }
}
