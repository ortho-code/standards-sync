<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Neon;

use OrthoCode\StandardsSync\Formats\Neon\NeonValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Neon's rendering; the unquoting and comment rules it shares with yaml are the inline scalar's to test. */
#[CoversClass(NeonValue::class)]
final class NeonValueTest extends TestCase
{
    #[DataProvider('renderedValues')]
    public function testRendersAScalarAsNeonText(bool|int|string $value, string $expected): void
    {
        self::assertSame($expected, NeonValue::render($value));
    }

    /** @return iterable<string, array{bool|int|string, string}> */
    public static function renderedValues(): iterable
    {
        yield 'true stays bare' => [true, 'true'];
        yield 'false stays bare' => [false, 'false'];
        yield 'an integer stays bare' => [7, '7'];
        yield 'a safe string stays bare' => ['vendor/acme/standards.neon', 'vendor/acme/standards.neon'];
        yield 'a string with spaces is single-quoted' => ['hello world', '\'hello world\''];
        yield 'a string holding a single quote is double-quoted' => ['it\'s', '"it\'s"'];
    }

    public function testRefusesAValueMixingBothQuoteStyles(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('mixes both quote styles');

        NeonValue::render('both \' and "');
    }
}
