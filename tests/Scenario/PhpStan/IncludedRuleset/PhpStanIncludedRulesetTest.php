<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\PhpStan\IncludedRuleset;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PhpStanIncludedRulesetTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a project without a config gets one created' => ['from-scratch', $config];
        yield 'an existing includes section gains the import' => ['insert-into-section', $config];
        yield 'a config without includes gains the section at the top' => ['creates-section', $config];
        yield 'an already-imported config stays put' => ['already-included', $config];
        yield 'a moved ruleset replaces its predecessor in place, keeping the line\'s comment' => ['replaces-a-moved-ruleset-in-place', $config];
        yield 'a ruleset no standard declares any more is retracted, and the project\'s include stays' => ['retracts-a-ruleset-no-longer-declared', $config];
        yield 'a second declaration adds its ruleset after the first' => ['merges-a-second-declaration', 'standards-sync-merged.php'];
        yield 'an includes list at the section\'s own indentation gains the import at that indentation' => ['inserts-into-a-zero-indent-list', $config];
    }
}
