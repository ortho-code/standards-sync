<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\PhpStan\MinLevel;

use OrthoCode\StandardsSync\Rules\PhpStan\MinLevel\PhpStanLevel;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpStanLevel::class)]
final class PhpStanLevelTest extends TestCase
{
    public function testHoldsAnIntWithinTheRange(): void
    {
        self::assertSame(6, PhpStanLevel::fromInt(6)->value());
    }

    #[DataProvider('outOfRangeLevels')]
    public function testRejectsAnIntOutsideTheRange(int $level): void
    {
        $this->expectException(InvalidArgumentException::class);

        PhpStanLevel::fromInt($level);
    }

    /** @return iterable<string, array{int}> */
    public static function outOfRangeLevels(): iterable
    {
        yield 'below zero' => [-1];
        yield 'above the highest level' => [11];
    }

    public function testMaxIsTheHighestLevel(): void
    {
        self::assertSame(10, PhpStanLevel::createMax()->value());
    }

    #[DataProvider('configValues')]
    public function testParsesALevelAsWrittenInAConfig(string $value, int $expected): void
    {
        self::assertSame($expected, PhpStanLevel::fromConfigValue($value)->value());
    }

    /** @return iterable<string, array{string, int}> */
    public static function configValues(): iterable
    {
        yield 'bare number' => ['6', 6];
        yield 'single-quoted number' => ['\'6\'', 6];
        yield 'double-quoted number' => ['"6"', 6];
        yield 'the max alias' => ['max', 10];
        yield 'the max alias in any case' => ['MAX', 10];
        yield 'the quoted max alias' => ['\'max\'', 10];
    }

    public function testRejectsAValueThatIsNotALevel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PhpStanLevel::fromConfigValue('%level%');
    }

    public function testComparesLevels(): void
    {
        self::assertTrue(PhpStanLevel::fromInt(8)->isAtLeast(PhpStanLevel::fromInt(7)));
        self::assertTrue(PhpStanLevel::fromInt(7)->isAtLeast(PhpStanLevel::fromInt(7)));
        self::assertFalse(PhpStanLevel::fromInt(6)->isAtLeast(PhpStanLevel::fromInt(7)));
    }
}
