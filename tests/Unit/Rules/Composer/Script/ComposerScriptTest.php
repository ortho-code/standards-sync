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
        self::assertSame('Runs "vendor/bin/standards-sync sync --check" in the composer script "app-check-standards", beside any commands the project adds.', self::rule()->description());
    }

    public function testTheDescriptionNamesTheCommandsThatAcceptArguments(): void
    {
        $rule = new ComposerScript(name: 'app-phpstan', commands: ['phpstan analyse'], acceptsArguments: true);

        self::assertSame('Runs "phpstan analyse" in the composer script "app-phpstan", beside any commands the project adds. "phpstan analyse" accepts extra arguments.', $rule->description());
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

        self::assertSame(['@app-sync-check', '@app-run-tests', '@app-lint'], $merged->entries());
        self::assertSame($expected, $merged->apply(FileContent::fromString('{}')));
        self::assertSame('Runs "@app-sync-check", "@app-run-tests", "@app-lint" in the composer script "app-checks", beside any commands the project adds.', $merged->description());
    }

    /** A new declaration that extends a retired one must never be taken for it, or the rule would retract what it has just declared. */
    public function testADeclaredCommandIsNeverRetractedByARetiredOneItExtends(): void
    {
        $manifest = FileContent::fromString(
            <<<'JSON'
                {
                    "scripts": {
                        "app-phpstan": [
                            "phpstan analyse --no-progress"
                        ]
                    }
                }
                JSON,
        );

        $rule = new ComposerScript(name: 'app-phpstan', commands: ['phpstan analyse --no-progress'])->withRetired(['phpstan analyse']);

        self::assertSame($manifest, $rule->apply($manifest));
    }

    public function testTheExplanationNamesWhatIsMissingWhatIsRetractedAndWhatStays(): void
    {
        $manifest = FileContent::fromString(
            <<<'JSON'
                {
                    "scripts": {
                        "app-checks": [
                            "@app-sync-check",
                            "@app-phpcs",
                            "@app-psalm"
                        ]
                    }
                }
                JSON,
        );

        $rule = new ComposerScript(name: 'app-checks', commands: ['@app-sync-check', '@app-run-tests'])->withRetired(['@app-phpcs']);

        self::assertSame(
            'It does not run "@app-run-tests" yet. It stops running "@app-phpcs", which no standard declares any more. The project\'s own "@app-psalm" stays.',
            $rule->explain($manifest),
        );
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
