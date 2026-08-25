<?php

declare(strict_types=1);

namespace StandardsSync\Rules\PhpUnit\PinnedAttributes;

use InvalidArgumentException;

/** One pinned root attribute: its name and the exact text the config must carry. */
final readonly class PinnedAttribute
{
    /** The spellings phpunit actually reads; every other spelling is silently false. */
    public const string SPELLING_TRUE = 'true';
    public const string SPELLING_FALSE = 'false';

    private const string VALID_NAME = '/^[A-Za-z_][A-Za-z0-9_.:-]*$/';

    private function __construct(
        private string $name,
        private string $value,
    ) {
    }

    /** Booleans render as phpunit's canonical "true"/"false" spelling, integers as decimal text. */
    public static function fromNameAndValue(string $name, bool|int|string $value): self
    {
        if (preg_match(self::VALID_NAME, $name) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not an XML attribute name.', $name));
        }

        $text = match (true) {
            is_bool($value) => $value ? self::SPELLING_TRUE : self::SPELLING_FALSE,
            is_int($value) => (string) $value,
            default => $value,
        };
        if ($text === '') {
            throw new InvalidArgumentException(sprintf('The pinned value for "%s" cannot be empty.', $name));
        }

        return new self($name, $text);
    }

    public function name(): string
    {
        return $this->name;
    }

    /** The exact text the attribute must carry. */
    public function value(): string
    {
        return $this->value;
    }
}
