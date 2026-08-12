<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Formats\Yaml;

use AlleKnalle\StandardsSync\Formats\Yaml\YamlIndent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(YamlIndent::class)]
final class YamlIndentTest extends TestCase
{
    public function testTheUnitTheFileUsesWins(): void
    {
        self::assertSame('    ', YamlIndent::fromLines(['deptrac:', '    paths: []']));
    }

    public function testFallsBackToTheYamlDefaultWhenNothingIsIndented(): void
    {
        self::assertSame('  ', YamlIndent::fromLines(['deptrac:']));
        self::assertSame('  ', YamlIndent::fromLines([]));
    }
}
