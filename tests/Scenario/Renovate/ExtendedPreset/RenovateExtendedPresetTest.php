<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Scenario\Renovate\ExtendedPreset;

use AlleKnalle\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class RenovateExtendedPresetTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a project without a config gets one created' => ['from-scratch', $config];
        yield 'an existing extends list gains the entry' => ['insert-into-extends', $config];
        yield 'a config without extends gains the list' => ['creates-extends-section', $config];
        yield 'an already-extended config stays put' => ['already-extended', $config];
        yield 'a json5-only repo is synced in place, nothing is created' => ['json5-only-repo', $config];
        yield 'an org preferring json5 creates that format, annotated' => ['from-scratch-json5', null];
        yield 'a compliant entry gains the enforced comment' => ['json5-comment-enforced', null];
    }
}
