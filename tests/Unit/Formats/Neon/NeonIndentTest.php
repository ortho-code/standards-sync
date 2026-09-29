<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Formats\Neon;

use OrthoCode\StandardsSync\Formats\Neon\NeonIndent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NeonIndent::class)]
final class NeonIndentTest extends TestCase
{
    public function testTheUnitTheFileUsesWins(): void
    {
        self::assertSame('    ', NeonIndent::fromLines(['parameters:', '    level: 6']));
    }

    public function testFallsBackToTheNeonDefaultWhenNothingIsIndented(): void
    {
        // The indent unit alone rather than file content, and a literal tab would be invisible where the escape names it.
        self::assertSame("\t", NeonIndent::fromLines(['parameters:']));
        self::assertSame("\t", NeonIndent::fromLines([]));
    }
}
