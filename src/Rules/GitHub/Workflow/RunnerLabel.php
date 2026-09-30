<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

/**
 * A GitHub-hosted runner label as a name, a version and a suffix: `ubuntu-24.04-arm` is `ubuntu`, `24.04` and `-arm`.
 * The suffix names another machine rather than another version, so only labels of one name and suffix compare, as Renovate compares them.
 */
final readonly class RunnerLabel
{
    private const string VERSIONED = '/^([A-Za-z]+)-(\d+(?:\.\d+)*)(-\S+)?$/';

    private function __construct(
        private string $name,
        private ?SpelledVersion $version,
        private string $suffix,
    ) {}

    public static function fromString(string $label): self
    {
        if (preg_match(self::VERSIONED, $label, $match) !== 1) {
            return new self($label, null, '');
        }

        return new self($match[1], SpelledVersion::fromString($match[2]), $match[3] ?? '');
    }

    /** Whether it names the same machine as another label: the same name and suffix, whatever the version. */
    public function isSameKind(self $other): bool
    {
        return $this->name === $other->name && $this->suffix === $other->suffix;
    }

    /** Whether it is a label of the minimum's kind at its version or later; null when either names no version, as `ubuntu-latest` does. */
    public function isAtLeast(self $minimum): ?bool
    {
        if (!$this->version instanceof SpelledVersion || !$minimum->version instanceof SpelledVersion) {
            return null;
        }

        return $this->isSameKind($minimum) && $this->version->isAtLeast($minimum->version);
    }
}
