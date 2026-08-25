<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Integration\Documentation;

use StandardsSync\Documentation\RuleCatalog;
use StandardsSync\Documentation\Source\RuleLibrary;
use StandardsSync\Documentation\Source\ScenarioTestSuite;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The committed catalog is generated from the rule library and the scenario suite; this holds them together so none can rot alone.
 * It also inherits the generator's refusals: a rule without scenario coverage fails this test, not just the manual regeneration.
 */
#[CoversClass(RuleCatalog::class)]
#[CoversClass(RuleLibrary::class)]
#[CoversClass(ScenarioTestSuite::class)]
final class RuleCatalogFreshnessTest extends TestCase
{
    public function testTheCommittedCatalogMatchesTheScenarioSuite(): void
    {
        $root = dirname(__DIR__, 3);

        $pages = RuleCatalog::fromProject($root)->pages();
        ksort($pages);

        $committed = [];
        $directory = $root . '/' . RuleCatalog::DIRECTORY;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            $committed[substr($file->getPathname(), strlen($directory) + 1)] = (string) file_get_contents($file->getPathname());
        }
        ksort($committed);

        self::assertSame($pages, $committed, 'The rule catalog is stale — regenerate it: composer app-generate-rule-catalog');
    }
}
