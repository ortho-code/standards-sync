<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Php;

use OrthoCode\StandardsSync\Formats\Php\DirAnchoredEntry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirAnchoredEntry::class)]
final class DirAnchoredEntryTest extends TestCase
{
    public function testRendersTheEntryAndKeepsThePath(): void
    {
        $entry = DirAnchoredEntry::fromRelativeString('vendor/acme/standards/config/rector.php');

        self::assertSame('__DIR__ . \'/vendor/acme/standards/config/rector.php\'', $entry->value());
        self::assertSame('vendor/acme/standards/config/rector.php', $entry->path()->value());
    }

    // The caller passes data, not PHP: expression text would break the rendered entry's quoting or be written verbatim into every consumer config.
    #[DataProvider('expressionTextPaths')]
    public function testRefusesExpressionTextInThePath(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('plain relative path');

        DirAnchoredEntry::fromRelativeString($path);
    }

    /** @return iterable<string, array{string}> */
    public static function expressionTextPaths(): iterable
    {
        yield '__DIR__' => ['__DIR__ . /vendor/acme/rector.php'];
        yield 'single quote' => ['vendor/a\'cme/rector.php'];
        yield 'double quote' => ['vendor/a"cme/rector.php'];
    }

    public function testRefusesAnAbsolutePath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('relative');

        DirAnchoredEntry::fromRelativeString('/vendor/acme/rector.php');
    }
}
