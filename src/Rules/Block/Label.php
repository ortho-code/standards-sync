<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Block;

use InvalidArgumentException;

/**
 * Namespaces a managed block so packages and layers can co-manage one file.
 * The charset is restricted so the label is always safe inside a marker line and its regex.
 */
final readonly class Label
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/^[A-Za-z0-9._-]+$/', $value) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'A label may only contain letters, digits, dot, underscore and hyphen; got "%s".',
                $value,
            ));
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
