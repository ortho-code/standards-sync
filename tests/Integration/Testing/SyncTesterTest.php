<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Integration\Testing;

use OrthoCode\StandardsSync\Rules\General\ManagedBlock\Label;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\ManagedBlock;
use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Testing\SyncTester;
use OrthoCode\StandardsSync\Testing\FileContent;
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
