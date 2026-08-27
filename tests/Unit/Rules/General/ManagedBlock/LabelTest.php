<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\General\ManagedBlock;

use OrthoCode\StandardsSync\Rules\General\ManagedBlock\Label;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Label::class)]
final class LabelTest extends TestCase
{
    #[DataProvider('validLabels')]
    public function testAcceptsAndReturnsAValidLabel(string $value): void
    {
        self::assertSame($value, Label::fromString($value)->value());
    }

    /** @return iterable<string, array{string}> */
    public static function validLabels(): iterable
    {
        yield 'package name' => ['acme-coding-standards'];
        yield 'with dot' => ['acme.editorconfig'];
        yield 'with underscore' => ['base_php'];
        yield 'alphanumeric' => ['Layer1'];
    }

    #[DataProvider('invalidLabels')]
    public function testRejectsUnsafeCharacters(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Label::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidLabels(): iterable
    {
        yield 'space' => ['has space'];
        yield 'newline' => ["two\nlines"];
        yield 'regex metacharacters' => ['a*b('];
        yield 'empty' => [''];
    }
}
