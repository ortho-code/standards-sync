<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Presentation\Cli;

use AlleKnalle\StandardsSync\Presentation\Cli\Application;
use AlleKnalle\StandardsSync\Presentation\Cli\DriftReport;
use AlleKnalle\StandardsSync\Presentation\Cli\SyncCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Tester\ApplicationTester;
use Tests\AlleKnalle\StandardsSync\Integration\IntegrationTestCase;

/** Drives the console application against a temp fixture: exit codes, no-write-in-check, apply-then-clean, help. */
#[CoversClass(SyncCommand::class)]
#[CoversClass(Application::class)]
#[CoversClass(DriftReport::class)]
final class SyncCommandTest extends IntegrationTestCase
{
    private const string FIXTURE_CONFIG = <<<'PHP'
        <?php

        declare(strict_types=1);

        use AlleKnalle\StandardsSync\Core\Block\Label;
        use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
        use AlleKnalle\StandardsSync\Core\Filesystem\Path;
        use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
        use AlleKnalle\StandardsSync\Core\Spec\FileSpec;

        $ruleSet = new class extends ComposableRuleSet {
            public function __construct()
            {
                $this->addSpec(new FileSpec(
                    Path::fromString('.editorconfig'),
                    Label::fromString('test'),
                    "root = true\n",
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
        [$exitCode, $output] = $this->runSync(['--check' => true]);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('CREATE', $output);
        self::assertStringContainsString('.editorconfig', $output);
    }

    public function testCheckNeverWrites(): void
    {
        $this->runSync(['--check' => true]);

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

        [$exitCode] = $this->runSync(['--check' => true]);

        self::assertSame(0, $exitCode);
    }

    public function testHelpDescribesTheSyncCommand(): void
    {
        [$exitCode, $output] = $this->runApplication(['command' => 'sync', '--help' => true]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('--check', $output);
    }

    /**
     * @param array<string, bool|string> $options
     * @return array{int, string}
     */
    private function runSync(array $options): array
    {
        return $this->runApplication(['command' => 'sync', '--root' => $this->workspace(), ...$options]);
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
