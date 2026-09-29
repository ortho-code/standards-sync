<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\Composer\Script;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ComposerScriptTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a manifest without the script gains it' => ['adds-the-script', $config];
        yield 'every command of a multi-command script gets its own line' => ['adds-a-multi-command-script', 'standards-sync-multiple.php'];
        yield 'a script running something else is rewritten, its neighbours untouched' => ['rewrites-a-drifted-script', $config];
        yield 'a script already running the declared commands is never touched' => ['leaves-a-matching-script', $config];
        yield 'a script written as a single string becomes the list form' => ['replaces-a-string-script', $config];
        yield 'a second declaration of the script adds its commands after the first\'s, a shared one counting once' => ['merges-a-second-declaration', null];
    }
}
