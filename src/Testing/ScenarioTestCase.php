<?php

declare(strict_types=1);

namespace StandardsSync\Testing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Base test case for verifying an org standards package against on-disk fixtures.
 * Each fixture is a directory with an input tree and an expected tree (plus its own standards-sync.php unless the scenario names one); fixtures live in a fixtures/ directory beside the concrete test class.
 * Extend it, return your scenarios from scenarios(), and each is synced and asserted to match.
 */
abstract class ScenarioTestCase extends TestCase
{
    /** The conventional fixtures directory beside the concrete test class. */
    private const string FIXTURES = 'fixtures';

    /** @return iterable<string, array{string, ?string}> */
    abstract public static function scenarios(): iterable;

    #[DataProvider('scenarios')]
    public function testSyncMatchesTheFixture(string $scenario, ?string $config): void
    {
        $fixtures = static::fixturesDirectory();
        $configFile = $config !== null ? $fixtures . '/' . $config : null;

        self::assertSame([], (new SyncFixtureTester())->diff($fixtures . '/' . $scenario, $configFile));
    }

    /** The fixtures directory beside the concrete test class. */
    public static function fixturesDirectory(): string
    {
        return dirname((string) (new ReflectionClass(static::class))->getFileName()) . '/' . self::FIXTURES;
    }
}
