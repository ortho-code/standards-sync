<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Unit\Rules\Composer\Script;

use StandardsSync\Rules\Composer\Script\ComposerScript;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ComposerScript::class)]
final class ComposerScriptTest extends TestCase
{
    public function testRefusesAScriptWithoutAName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs a name');

        new ComposerScript(name: ' ', commands: ['vendor/bin/standards-sync sync --check']);
    }

    public function testRefusesAScriptWithoutCommands(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('needs at least one command');

        new ComposerScript(name: 'app-check-standards', commands: []);
    }

    public function testAbstainsWithoutAManifest(): void
    {
        self::assertNull(self::rule()->apply(null));
    }

    public function testTheDescriptionNamesTheScriptAndWhatItRuns(): void
    {
        self::assertSame('Runs "vendor/bin/standards-sync sync --check" as the composer script "app-check-standards".', self::rule()->description());
    }

    private static function rule(): ComposerScript
    {
        return new ComposerScript(name: 'app-check-standards', commands: ['vendor/bin/standards-sync sync --check']);
    }
}
