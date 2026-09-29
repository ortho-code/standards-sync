<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\Engine\ListContributions;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/** Engine-level list contributions: declarations of one list merge across rule sets, and declaration order decides where each one's entries go. */
#[CoversNothing]
final class ListContributionsTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        yield 'a standard declared after the tier adds its commands after the tier\'s' => ['tier-declared-first', 'standards-sync-tier-first.php'];
        yield 'a standard declared before the tier puts its commands first' => ['tier-declared-last', 'standards-sync-tier-last.php'];
    }
}
