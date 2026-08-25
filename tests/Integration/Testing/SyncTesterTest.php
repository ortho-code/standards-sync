<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Integration\Testing;

use StandardsSync\Rules\General\ManagedBlock\Label;
use StandardsSync\Rules\General\ManagedBlock\ManagedBlock;
use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\Rule\FileTarget;
use StandardsSync\Core\RuleSet\ComposableRuleSet;
use StandardsSync\Testing\SyncTester;
use StandardsSync\Testing\FileContent;
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
                $this->addRule(new ManagedBlock(
                    FileTarget::fromString('.editorconfig'),
                    Label::fromString('test'),
                    FileContent::fromString('root = true'),
                ));
            }
        });
    }
}
