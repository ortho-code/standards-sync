<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Integration\Presentation\Cli\Command;

use OrthoCode\StandardsSync\Presentation\Cli\Application;
use OrthoCode\StandardsSync\Presentation\Cli\Command\SyncCommand;
use OrthoCode\StandardsSync\Presentation\Cli\Output\DriftReport;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Tester\ApplicationTester;
use Tests\OrthoCode\StandardsSync\Integration\IntegrationTestCase;

/** Drives the console application against a temp fixture: exit codes, no-write-in-check, apply-then-clean, help. */
#[CoversClass(SyncCommand::class)]
#[CoversClass(Application::class)]
#[CoversClass(DriftReport::class)]
final class SyncCommandTest extends IntegrationTestCase
{
    private const string FIXTURE_CONFIG = <<<'PHP'
        <?php

        declare(strict_types=1);

        use OrthoCode\StandardsSync\Rules\General\ManagedBlock\Label;
        use OrthoCode\StandardsSync\Rules\General\ManagedBlock\ManagedBlock;
        use OrthoCode\StandardsSync\Core\Config\SyncConfig;
        use OrthoCode\StandardsSync\Core\Rule\FileTarget;
        use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;

        $ruleSet = new class extends ComposableRuleSet {
            public function __construct()
            {
                $this->addRule(new ManagedBlock(
                    FileTarget::fromString('.editorconfig'),
                    Label::fromString('test'),
                    'root = true',
                ));
            }
        };

        return SyncConfig::create()->withRuleSet($ruleSet);
        PHP;

    private const string ABSTAINING_CONFIG = <<<'PHP'
        <?php

        declare(strict_types=1);

        use OrthoCode\StandardsSync\Core\Config\SyncConfig;
        use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
        use OrthoCode\StandardsSync\Rules\Composer\Requirement\ComposerRequirement;
        use OrthoCode\StandardsSync\Rules\Composer\Requirement\VersionConstraint;

        $ruleSet = new class extends ComposableRuleSet {
            public function __construct()
            {
                $this->addRule(new ComposerRequirement(
                    package: 'phpstan/phpstan',
                    constraint: VersionConstraint::fromString('^2.5'),
                ));
            }
        };

        return SyncConfig::create()->withRuleSet($ruleSet);
        PHP;

    protected function setUp(): void
    {
        $this->writeToWorkspace('standards-sync.php', self::FIXTURE_CONFIG);
    }

    public function testCheckReportsDriftAndExitsNonZeroWhenAManagedFileIsMissing(): void
    {
        [$exitCode, $output] = $this->runSync([
            '--check' => true,
        ]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('CREATE', $output);
        self::assertStringContainsString('.editorconfig', $output);
        self::assertStringContainsString('ManagedBlock: Places the managed "test" block', $output);
    }

    public function testCheckNeverWrites(): void
    {
        $this->runSync([
            '--check' => true,
        ]);

        self::assertFileDoesNotExist($this->workspace() . '/.editorconfig');
    }

    public function testApplyWritesTheManagedFile(): void
    {
        [$exitCode] = $this->runSync([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('root = true', (string) file_get_contents($this->workspace() . '/.editorconfig'));
    }

    public function testCheckExitsZeroAfterApply(): void
    {
        $this->runSync([]);

        [$exitCode] = $this->runSync([
            '--check' => true,
        ]);

        self::assertSame(0, $exitCode);
    }

    /** An abstention is reported but is not drift, so a repo the rule has no opinion about still passes CI. */
    public function testCheckNotesAnAbstentionAndStillExitsZero(): void
    {
        $this->writeToWorkspace('standards-sync.php', self::ABSTAINING_CONFIG);

        [$exitCode, $output] = $this->runSync([
            '--check' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('NOTE ./composer.json does not exist; nothing was enforced there (ComposerRequirement).', $output);
    }

    /** The note is a standing fact, so it is told on a writing run too, not only under --check. */
    public function testSyncNotesAnAbstentionAsWell(): void
    {
        $this->writeToWorkspace('standards-sync.php', self::ABSTAINING_CONFIG);

        [$exitCode, $output] = $this->runSync([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('NOTE ./composer.json does not exist; nothing was enforced there (ComposerRequirement).', $output);
        self::assertFileDoesNotExist($this->workspace() . '/composer.json');
    }

    public function testHelpDescribesTheSyncCommand(): void
    {
        [$exitCode, $output] = $this->runApplication([
            'command' => 'sync',
            '--help' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('--check', $output);
    }

    /**
     * @param array<string, bool|string> $options
     * @return array{int, string}
     */
    private function runSync(array $options): array
    {
        return $this->runApplication([
            'command' => 'sync',
            '--root' => $this->workspace(),
            ...$options,
        ]);
    }

    /**
     * @param array<string, bool|string> $input
     * @return array{int, string}
     */
    private function runApplication(array $input): array
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $exitCode = $tester->run($input);

        return [$exitCode, $tester->getDisplay()];
    }
}
