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
        $aggregate = 'standards-sync-aggregate.php';

        yield 'a manifest without the script gains it' => ['adds-the-script', $config];
        yield 'every command of a multi-command script gets its own line' => ['adds-a-multi-command-script', 'standards-sync-multiple.php'];
        yield 'a script running other commands keeps them, the declared one inserted first' => ['keeps-the-projects-commands', $config];
        yield 'a script already running the declared commands keeps its bytes; only the lock is written' => ['leaves-a-matching-script', $config];
        yield 'a string script running something else becomes a list holding both' => ['extends-a-string-script', $config];
        yield 'a string script running the declared command is left as written' => ['leaves-a-matching-string-script', $config];
        yield 'a command the project added is kept where the project put it' => ['keeps-a-command-the-project-added', $aggregate];
        yield 'a missing leading command is inserted first' => ['inserts-a-missing-leading-command', $aggregate];
        yield 'a missing command is inserted after the one declared before it' => ['inserts-a-missing-command-after-its-predecessor', $aggregate];
        yield 'a command the lock records and nobody declares now is retracted' => ['retracts-a-command-no-longer-declared', $aggregate];
        yield 'a retired command is retracted with the arguments the project added to it' => ['retracts-a-retired-command-with-arguments', $aggregate];
        yield 'a changed declaration replaces the command it retires' => ['replaces-a-changed-declaration', null];
        yield 'a declared command is inserted beside the project\'s variant of it' => ['inserts-the-declared-command-beside-a-variant', null];
        yield 'a second declaration of the script adds its commands after the first\'s, a shared one counting once' => ['merges-a-second-declaration', null];
    }
}
