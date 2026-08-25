<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Scenario\PhpStan\MinLevel;

use StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class PhpStanMinLevelTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a lower level is raised to the floor' => ['raises-a-lower-level', $config];
        yield 'a stricter level is never touched' => ['leaves-a-stricter-level', $config];
        yield 'a missing level is written' => ['writes-a-missing-level', $config];
        yield 'no phpstan config: one is created carrying the floor' => ['creates-the-config', $config];
        yield 'the org comment is written with a raised level' => ['writes-the-comment-on-a-raise', 'standards-sync-comment.php'];
        yield 'a deviating value and comment revert together' => ['rewrites-value-and-comment-together', 'standards-sync-comment.php'];
        yield 'a compliant level gains the org comment untouched' => ['comments-a-compliant-level', 'standards-sync-comment.php'];
    }
}
