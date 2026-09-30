<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\GitHub\Workflow;

/**
 * An action or reusable-workflow reference, `owner/repo[/path]@ref`, and the version it names: its tag's, or for a commit SHA, its pin comment's.
 * A reference without a ref — a local path, a Docker image — names the action alone.
 */
final readonly class ActionReference
{
    private const string REF_SEPARATOR = '@';

    /** A full commit SHA, which names no version by itself. */
    private const string COMMIT_SHA = '/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/';

    /** The version a pin comment names, in the spellings Renovate writes and reads: `v7.0.1`, `pin @v7.0.1`, `tag=v7.0.1`. */
    private const string PIN_COMMENT = '/^\s*(?:pin\s+|tag\s*=\s*)?@?(v?\d+(?:\.\d+)*)(?:\s|$)/';

    private function __construct(
        private string $action,
        private ?SpelledVersion $version,
        private bool $versionInComment,
    ) {}

    /** @param string|null $comment the comment after the reference on its line, without its `#` */
    public static function fromUses(string $uses, ?string $comment): self
    {
        $at = strrpos($uses, self::REF_SEPARATOR);
        if ($at === false) {
            return new self($uses, null, false);
        }

        $action = substr($uses, 0, $at);
        $ref = substr($uses, $at + 1);
        if (preg_match(self::COMMIT_SHA, $ref) !== 1) {
            return new self($action, SpelledVersion::fromString($ref), false);
        }
        $pinned = $comment !== null && preg_match(self::PIN_COMMENT, $comment, $match) === 1 ? SpelledVersion::fromString($match[1]) : null;

        return new self($action, $pinned, $pinned instanceof SpelledVersion);
    }

    /** Whether the reference is the same action, its path included, whatever version it names. */
    public function isSameAction(self $other): bool
    {
        return $this->action === $other->action;
    }

    /** Whether the version it names is read from its pin comment, which then belongs to the reference. */
    public function namesVersionInComment(): bool
    {
        return $this->versionInComment;
    }

    /** Whether it is the same action at the minimum's version or later; null when either names no version to compare. */
    public function isAtLeast(self $minimum): ?bool
    {
        if (!$this->isSameAction($minimum)) {
            return false;
        }
        if (!$this->version instanceof SpelledVersion || !$minimum->version instanceof SpelledVersion) {
            return null;
        }

        return $this->version->isAtLeast($minimum->version);
    }
}
