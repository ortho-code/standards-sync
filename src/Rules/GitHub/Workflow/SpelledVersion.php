<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

/**
 * A version spelled as dotted numbers, an optional `v` before them, compared on the components both sides spell.
 * So `7` satisfies `7.2.0`, as a moving major tag does, and `7.1` does not.
 */
final readonly class SpelledVersion
{
    private const string SPELLING = '/^v?(\d+(?:\.\d+)*)$/';

    private const string SEPARATOR = '.';

    /** @param list<int> $components */
    private function __construct(
        private array $components,
    ) {}

    /** Null for text that spells no version: a branch, a commit SHA, `latest`. */
    public static function fromString(string $text): ?self
    {
        if (preg_match(self::SPELLING, $text, $match) !== 1) {
            return null;
        }

        return new self(array_map(intval(...), explode(self::SEPARATOR, $match[1])));
    }

    public function isAtLeast(self $minimum): bool
    {
        $shared = min(count($this->components), count($minimum->components));
        for ($index = 0; $index < $shared; $index++) {
            if ($this->components[$index] !== $minimum->components[$index]) {
                return $this->components[$index] > $minimum->components[$index];
            }
        }

        return true;
    }
}
