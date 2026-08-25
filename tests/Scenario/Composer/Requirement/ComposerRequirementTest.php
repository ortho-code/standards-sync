<?php

declare(strict_types=1);

namespace Tests\StandardsSync\Scenario\Composer\Requirement;

use StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ComposerRequirementTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';
        $runtime = 'standards-sync-runtime.php';

        yield 'the requirement is added to an existing require-dev' => ['adds-to-require-dev', $config];
        yield 'a manifest without require-dev gains the section' => ['creates-the-require-dev-section', $config];
        yield 'a constraint that meets the minimum is never touched' => ['leaves-a-meeting-constraint', $config];
        yield 'a constraint below the minimum is raised to it' => ['raises-a-constraint-below-the-minimum', $config];
        yield 'a branch constraint meets no minimum and is raised' => ['raises-a-branch-constraint', $config];
        yield 'raising keeps an allowed newer major' => ['keeps-a-newer-major-when-raising', $config];
        yield 'a runtime requirement already covers a development one' => ['leaves-a-runtime-requirement-alone', $config];
        yield 'a package required in both sections loses the redundant one' => ['drops-the-redundant-duplicate', $config];
        yield 'the manifest keeps its own indentation' => ['keeps-the-manifests-own-indentation', $config];
        yield 'a package key written with an escaped slash is the same member' => ['matches-an-escaped-package-key', $config];
        yield 'a root that is not a composer project gets no manifest' => ['no-manifest-creates-nothing', $config];
        yield 'a runtime requirement moves out of require-dev' => ['moves-a-requirement-into-require', $runtime];
    }
}
