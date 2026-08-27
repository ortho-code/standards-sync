<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\Psalm\LoosestErrorLevel;

use OrthoCode\StandardsSync\Rules\Psalm\LoosestErrorLevel\PsalmErrorLevel;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PsalmErrorLevel::class)]
final class PsalmErrorLevelTest extends TestCase
{
    public function testHoldsAnIntWithinTheRange(): void
    {
        self::assertSame(4, PsalmErrorLevel::fromInt(4)->value());
    }

    #[DataProvider('outOfRangeLevels')]
    public function testRejectsAnIntOutsideTheRange(int $level): void
    {
        $this->expectException(InvalidArgumentException::class);

        PsalmErrorLevel::fromInt($level);
    }

    /** @return iterable<string, array{int}> */
    public static function outOfRangeLevels(): iterable
    {
        yield 'below the strictest level' => [0];
        yield 'above the loosest level' => [9];
    }

    public function testTheDefaultIsWhatAnAbsentAttributeMeans(): void
    {
        self::assertSame(2, PsalmErrorLevel::createDefault()->value());
    }

    public function testParsesABareIntegerConfigValue(): void
    {
        self::assertSame(3, PsalmErrorLevel::fromConfigValue('3')->value());
    }

    #[DataProvider('invalidConfigValues')]
    public function testRejectsAConfigValueThatIsNoLevel(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        PsalmErrorLevel::fromConfigValue($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidConfigValues(): iterable
    {
        yield 'an alias' => ['max'];
        yield 'an empty value' => [''];
        yield 'a negative number' => ['-1'];
        yield 'a decimal' => ['3.5'];
        yield 'an out-of-range digit' => ['9'];
    }

    public function testAtMostIsNumericallyAtMost(): void
    {
        $loosest = PsalmErrorLevel::fromInt(4);

        self::assertTrue(PsalmErrorLevel::fromInt(2)->isAtMost($loosest));
        self::assertTrue(PsalmErrorLevel::fromInt(4)->isAtMost($loosest));
        self::assertFalse(PsalmErrorLevel::fromInt(8)->isAtMost($loosest));
    }
}
