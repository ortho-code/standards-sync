<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\Composer\Script;

use OrthoCode\StandardsSync\Rules\Composer\Script\ComposerScript;
use OrthoCode\StandardsSync\Testing\FileContent;
use InvalidArgumentException;
use LogicException;
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

    public function testTheListIsTheScriptInTheScriptsSection(): void
    {
        self::assertSame('scripts.app-check-standards', self::rule()->listKey());
    }

    public function testAMergedDeclarationRunsItsCommandsAfterTheEarlierOnesCountingASharedOneOnce(): void
    {
        $merged = new ComposerScript(name: 'app-checks', commands: ['@app-sync-check', '@app-run-tests'])
            ->withMerged(new ComposerScript(name: 'app-checks', commands: ['@app-run-tests', '@app-lint']));

        $expected = FileContent::fromString(
            <<<'JSON'
                {
                    "scripts": {
                        "app-checks": [
                            "@app-sync-check",
                            "@app-run-tests",
                            "@app-lint"
                        ]
                    }
                }
                JSON,
        );

        self::assertSame($expected, $merged->apply(FileContent::fromString('{}')));
        self::assertSame('Runs "@app-sync-check", "@app-run-tests", "@app-lint" as the composer script "app-checks".', $merged->description());
    }

    public function testRefusesToMergeADeclarationOfAnotherScript(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Only declarations of the composer script "app-checks" merge into it.');

        new ComposerScript(name: 'app-checks', commands: ['@app-sync-check'])
            ->withMerged(new ComposerScript(name: 'app-lint', commands: ['@app-sync-check']));
    }

    private static function rule(): ComposerScript
    {
        return new ComposerScript(name: 'app-check-standards', commands: ['vendor/bin/standards-sync sync --check']);
    }
}
