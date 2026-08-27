<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\Psalm\LoosestErrorLevel;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PsalmLoosestErrorLevelTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a looser level is lowered to the limit' => ['lowers-a-looser-level', $config];
        yield 'a stricter level is never touched' => ['leaves-a-stricter-level', $config];
        yield 'an equal level is never touched' => ['leaves-an-equal-level', $config];
        yield 'an absent errorLevel is made explicit as the default' => ['makes-the-default-explicit', $config];
        yield 'an absent errorLevel meets a limit stricter than the default' => ['makes-the-default-explicit-at-a-stricter-limit', 'standards-sync-strictest.php'];
        yield 'no psalm config: one is created at the limit' => ['creates-the-config', $config];
    }
}
