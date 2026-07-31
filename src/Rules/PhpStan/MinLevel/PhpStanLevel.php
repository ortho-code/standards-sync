<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\PhpStan\MinLevel;

use InvalidArgumentException;

/**
 * A PHPStan rule level: the bounded 0..10 range, with "max" as the alias for the highest.
 * All level grammar lives here — bounds, the max alias, quoted config values — so rules compare levels instead of parsing primitives.
 */
final readonly class PhpStanLevel
{
    /** What the alias currently resolves to: PHPStan's highest level. */
    private const int MAX_LEVEL = 10;

    /** PHPStan's alias for the highest level, as written in configs. */
    private const string MAX_ALIAS = 'max';

    private function __construct(private int $level)
    {
    }

    public static function fromInt(int $level): self
    {
        if ($level < 0 || $level > self::MAX_LEVEL) {
            throw new InvalidArgumentException(sprintf('A PHPStan level must be between 0 and %d, got %d.', self::MAX_LEVEL, $level));
        }

        return new self($level);
    }

    public static function createMax(): self
    {
        return new self(self::MAX_LEVEL);
    }

    /** Parses a level as written in a config: bare or quoted, a number or the "max" alias. */
    public static function fromConfigValue(string $value): self
    {
        $bare = trim($value);
        if (preg_match('/^([\'"])(.*)\1$/', $bare, $match) === 1) {
            $bare = $match[2];
        }

        if (strtolower($bare) === self::MAX_ALIAS) {
            return self::createMax();
        }
        if (ctype_digit($bare)) {
            return self::fromInt((int) $bare);
        }

        throw new InvalidArgumentException(sprintf('"%s" is not a PHPStan level (0-%d or "%s").', $value, self::MAX_LEVEL, self::MAX_ALIAS));
    }

    public function value(): int
    {
        return $this->level;
    }

    public function isAtLeast(self $minimum): bool
    {
        return $this->level >= $minimum->level;
    }
}
