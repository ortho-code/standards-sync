<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\Engine\ListContributions;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** Engine-level list contributions: declarations of one list merge across rule sets, and each root's standards-sync.lock records what they declared. */
#[CoversNothing]
final class ListContributionsTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a standard declared after the tier adds its commands after the tier\'s' => ['tier-declared-first', 'standards-sync-tier-first.php'];
        yield 'a standard declared before the tier puts its commands first' => ['tier-declared-last', 'standards-sync-tier-last.php'];
        yield 'without a lock nothing is retracted, and the first sync writes one' => ['without-a-lock-retracts-nothing', $config];
        yield 'an in-sync manifest beside a stale lock drifts in the lock alone' => ['a-stale-lock-drifts-alone', $config];
        yield 'a list no standard declares any more drops out of the lock and stays in the file' => ['a-list-no-standard-declares-stays', $config];
        yield 'an absent manifest records nothing, so no lock is created' => ['an-absent-manifest-creates-no-lock', $config];
        yield 'a config without list-contributing rules creates no lock' => ['no-contributing-rule-creates-no-lock', null];
    }
}
