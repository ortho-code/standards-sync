<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Neon;

use OrthoCode\StandardsSync\Formats\Neon\NeonValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
        yield 'a string with spaces is single-quoted' => ['hello world', "'hello world'"];
        yield 'a string holding a single quote is double-quoted' => ["it's", '"it\'s"'];
    }

    public function testRefusesAValueMixingBothQuoteStyles(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mixes both quote styles');

        NeonValue::render('both \' and "');
    }

    #[DataProvider('unquotedValues')]
    public function testUnquotesAWrittenValue(string $written, string $expected): void
    {
        self::assertSame($expected, NeonValue::unquote($written));
    }

    /** @return iterable<string, array{string, string}> */
    public static function unquotedValues(): iterable
    {
        yield 'a bare value stays as is' => ['6', '6'];
        yield 'surrounding whitespace is trimmed' => [' 6 ', '6'];
        yield 'single quotes are stripped' => ["'6'", '6'];
        yield 'double quotes are stripped' => ['"6"', '6'];
        yield 'inner quotes are kept' => ["'it''s'", "it''s"];
        yield 'an unmatched quote is kept' => ["'6", "'6"];
    }

    #[DataProvider('splitLines')]
    public function testSplitsAValueFromItsTrailingComment(string $text, string $value, string $comment): void
    {
        self::assertSame([$value, $comment], NeonValue::splitTrailingComment($text));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function splitLines(): iterable
    {
        yield 'no comment' => [' 6', ' 6', ''];
        yield 'a plain comment' => [' 6 # keep in step with CI', ' 6', ' # keep in step with CI'];
        yield 'a hash inside single quotes is content' => [" '~foo#bar~'", " '~foo#bar~'", ''];
        yield 'a hash inside double quotes is content' => [' "~foo#bar~"', ' "~foo#bar~"', ''];
        yield 'a comment after a quoted hash value' => [" '~foo#bar~' # pattern", " '~foo#bar~'", ' # pattern'];
        yield 'an escaped quote does not end a double-quoted value' => [' "a\\"#b" # c', ' "a\\"#b"', ' # c'];
        yield 'a quote inside the comment stays in the comment' => [" 4 # don't touch", ' 4', " # don't touch"];
    }
}
