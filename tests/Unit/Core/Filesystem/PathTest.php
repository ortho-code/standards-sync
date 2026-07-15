<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Core\Filesystem;

use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Path::class)]
final class PathTest extends TestCase
{
    #[DataProvider('joinScenarios')]
    public function testJoinCollapsesTheSlashBetweenSegments(string $base, string $segment, string $expected): void
    {
        self::assertSame($expected, Path::fromString($base)->join(Path::fromString($segment))->value());
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function joinScenarios(): iterable
    {
        yield 'trailing slash on base' => ['/a/b/', '.editorconfig', '/a/b/.editorconfig'];
        yield 'no trailing slash on base' => ['/a/b', '.editorconfig', '/a/b/.editorconfig'];
        yield 'relative dot base' => ['.', '.editorconfig', './.editorconfig'];
    }

    #[DataProvider('absoluteScenarios')]
    public function testKnowsWhetherItIsAbsolute(string $path, bool $expected): void
    {
        self::assertSame($expected, Path::fromString($path)->isAbsolute());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function absoluteScenarios(): iterable
    {
        yield 'absolute' => ['/a', true];
        yield 'relative' => ['libraries/x', false];
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsAnInvalidPath(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        Path::fromString($value);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidPaths(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => ['   '];
    }
}
