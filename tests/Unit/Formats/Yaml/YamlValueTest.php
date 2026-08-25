<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Unit\Formats\Yaml;

use StandardsSync\Formats\Yaml\YamlValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlValue::class)]
final class YamlValueTest extends TestCase
{
    #[DataProvider('unquotedValues')]
    public function testUnquotesAWrittenValue(string $written, string $expected): void
    {
        self::assertSame($expected, YamlValue::unquote($written));
    }

    /** @return iterable<string, array{string, string}> */
    public static function unquotedValues(): iterable
    {
        yield 'a bare value stays as is' => ['vendor/acme/standards/deptrac.yaml', 'vendor/acme/standards/deptrac.yaml'];
        yield 'surrounding whitespace is trimmed' => [' 6 ', '6'];
        yield 'single quotes are stripped' => ["'6'", '6'];
        yield 'double quotes are stripped' => ['"6"', '6'];
        yield 'inner quotes are kept' => ["'it''s'", "it''s"];
        yield 'an unmatched quote is kept' => ["'6", "'6"];
    }

    #[DataProvider('splitLines')]
    public function testSplitsAValueFromItsTrailingComment(string $text, string $value, string $comment): void
    {
        self::assertSame([$value, $comment], YamlValue::splitTrailingComment($text));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function splitLines(): iterable
    {
        yield 'no comment' => [' 6', ' 6', ''];
        yield 'a plain comment' => [' 6 # keep in step with CI', ' 6', ' # keep in step with CI'];
        yield 'a hash not preceded by whitespace is content' => ['foo#bar', 'foo#bar', ''];
        yield 'a comment after an embedded hash' => ['foo#bar # note', 'foo#bar', ' # note'];
        yield 'a hash at the start opens a comment' => ['# all comment', '', '# all comment'];
        yield 'a hash inside single quotes is content' => [" '~foo #bar~'", " '~foo #bar~'", ''];
        yield 'a hash inside double quotes is content' => [' "~foo #bar~"', ' "~foo #bar~"', ''];
        yield 'a comment after a quoted hash value' => [" '~foo #bar~' # pattern", " '~foo #bar~'", ' # pattern'];
        yield 'an escaped quote does not end a double-quoted value' => [' "a\\" #b" # c', ' "a\\" #b"', ' # c'];
        yield 'a quote inside the comment stays in the comment' => [" 4 # don't touch", ' 4', " # don't touch"];
    }
}
