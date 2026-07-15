<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Resolve;

/**
 * Unions specs line by line, keeping the first occurrence of each non-empty line.
 * Suited to append-style files such as .gitignore where order matters but duplicates do not.
 */
final class LineUnionMerger implements ContentMerger
{
    public function merge(array $specs): string
    {
        $seen = [];
        $lines = [];
        foreach ($specs as $spec) {
            foreach (preg_split('/\R/', $spec->content()) ?: [] as $line) {
                if ($line !== '' && isset($seen[$line])) {
                    continue;
                }
                if ($line !== '') {
                    $seen[$line] = true;
                }
                $lines[] = $line;
            }
        }

        return rtrim(implode("\n", $lines), "\n");
    }
}
