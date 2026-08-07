<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Engine;

use AlleKnalle\StandardsSync\Core\Filesystem\Filesystem;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use RuntimeException;

/**
 * Resolves a FileTarget against one root to the file the standard belongs in.
 * A lone existing candidate wins whatever its name; a dist and a non-dist file together resolve to the dist file — the committed home — recording the non-dist file that shadows it for tool runs; when none exist the first candidate is the creation target.
 * Several existing files on the same side of the dist convention are a confused repo, refused loudly.
 */
final readonly class TargetResolver
{
    /** The dotted file-name segment marking a committed dist variant (phpstan.neon.dist, psalm.dist.xml). */
    private const string DIST_SEGMENT = 'dist';

    public function __construct(private Filesystem $filesystem)
    {
    }

    public function resolve(Path $root, FileTarget $target): ResolvedTarget
    {
        $dist = [];
        $nonDist = [];
        foreach ($target->candidates() as $candidate) {
            $path = $root->join($candidate);
            $current = $this->filesystem->read($path);
            if ($current === null) {
                continue;
            }

            if ($this->isDistVariant($candidate)) {
                $dist[] = new ResolvedTarget($path, $current);
            } else {
                $nonDist[] = new ResolvedTarget($path, $current);
            }
        }

        if (count($dist) > 1) {
            throw new RuntimeException(sprintf('Both %s exist; the standard has one committed home — remove all but one.', $this->pathList($dist)));
        }

        if (count($nonDist) > 1) {
            throw new RuntimeException(sprintf('Both %s exist for one target; remove all but one.', $this->pathList($nonDist)));
        }

        if ($dist !== [] && $nonDist !== []) {
            return new ResolvedTarget($dist[0]->path(), $dist[0]->current(), shadowedBy: $nonDist[0]->path());
        }

        if ($dist !== []) {
            return $dist[0];
        }

        if ($nonDist !== []) {
            return $nonDist[0];
        }

        return new ResolvedTarget($root->join($target->candidates()[0]), null);
    }

    /** A file name follows the dist convention when its final path component carries a dotted segment exactly "dist"; directories named dist do not count. */
    private function isDistVariant(Path $candidate): bool
    {
        return in_array(self::DIST_SEGMENT, explode('.', basename($candidate->value())), true);
    }

    /** @param non-empty-list<ResolvedTarget> $targets */
    private function pathList(array $targets): string
    {
        return implode(' and ', array_map(static fn (ResolvedTarget $target): string => sprintf('"%s"', $target->path()->value()), $targets));
    }
}
