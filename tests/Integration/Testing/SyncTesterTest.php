<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Integration\Testing;

use AlleKnalle\StandardsSync\Core\Block\Label;
use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Core\Spec\FileSpec;
use AlleKnalle\StandardsSync\Testing\SyncTester;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyncTester::class)]
final class SyncTesterTest extends TestCase
{
    public function testSyncAppliesTheConfigAndReturnsTheResultingFiles(): void
    {
        $result = (new SyncTester())->sync($this->config());

        self::assertArrayHasKey('./.editorconfig', $result);
        self::assertStringContainsString('root = true', $result['./.editorconfig']);
    }

    public function testPlanReportsDriftForAStaleFile(): void
    {
        $plan = (new SyncTester())->plan($this->config(), ['./.editorconfig' => 'stale']);

        self::assertTrue($plan->hasDrift());
    }

    private function config(): SyncConfig
    {
        return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
            public function __construct()
            {
                $this->addSpec(new FileSpec(
                    Path::fromString('.editorconfig'),
                    Label::fromString('test'),
                    "root = true\n",
                ));
            }
        });
    }
}
