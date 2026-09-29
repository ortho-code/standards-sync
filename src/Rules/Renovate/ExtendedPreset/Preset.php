<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Renovate\ExtendedPreset;

use OrthoCode\StandardsSync\Core\Text\Lines;
use InvalidArgumentException;

/**
 * One preset the renovate config extends, with the comment enforced on its entry's line where the grammar has comments.
 */
final readonly class Preset
{
    private function __construct(
        private string $reference,
        private ?string $comment,
    ) {}

    public static function fromReference(string $reference, ?string $comment = null): self
    {
        if (trim($reference) === '' || str_contains($reference, Lines::LINE_BREAK)) {
            throw new InvalidArgumentException('A preset reference is one non-empty line.');
        }

        if ($comment !== null && (trim($comment) === '' || str_contains($comment, Lines::LINE_BREAK))) {
            throw new InvalidArgumentException('A rule comment is one non-empty line.');
        }

        return new self($reference, $comment);
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function comment(): ?string
    {
        return $this->comment;
    }
}
