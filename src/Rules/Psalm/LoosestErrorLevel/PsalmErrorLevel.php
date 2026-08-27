<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Psalm\LoosestErrorLevel;

use InvalidArgumentException;

/**
 * A Psalm error level: the bounded 1..8 range on an inverted scale — 1 is strictest, 8 is loosest.
 * All level grammar lives here — bounds, the strict integer form, the implicit default of an absent attribute — so rules compare levels instead of parsing primitives.
 */
final readonly class PsalmErrorLevel
{
    private const int STRICTEST = 1;
    private const int LOOSEST = 8;

    /** The level psalm applies when the errorLevel attribute is absent. */
    private const int DEFAULT_LEVEL = 2;

    private function __construct(private int $level)
    {
    }

    public static function fromInt(int $level): self
    {
        if ($level < self::STRICTEST || $level > self::LOOSEST) {
            throw new InvalidArgumentException(sprintf('A Psalm error level must be between %d and %d, got %d.', self::STRICTEST, self::LOOSEST, $level));
        }

        return new self($level);
    }

    public static function createDefault(): self
    {
        return new self(self::DEFAULT_LEVEL);
    }

    /** Parses a level as written in the errorLevel attribute: a bare integer, no aliases — psalm validates the same way. */
    public static function fromConfigValue(string $value): self
    {
        if (!ctype_digit($value)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a Psalm error level (%d-%d).', $value, self::STRICTEST, self::LOOSEST));
        }

        return self::fromInt((int) $value);
    }

    public function value(): int
    {
        return $this->level;
    }

    /** Numerically at most — on the inverted scale: at least as strict. */
    public function isAtMost(self $loosest): bool
    {
        return $this->level <= $loosest->level;
    }
}
