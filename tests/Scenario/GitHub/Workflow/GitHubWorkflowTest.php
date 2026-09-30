<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Scenario\GitHub\Workflow;

use OrthoCode\StandardsSync\Testing\ScenarioTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class GitHubWorkflowTest extends ScenarioTestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function scenarios(): iterable
    {
        $config = 'standards-sync.php';

        yield 'a project without the workflow gets it as declared, comments included' => ['creates-the-workflow', $config];
        yield 'the project\'s own triggers, keys, inputs, steps and jobs stay beside the declared ones' => ['keeps-the-projects-additions', $config];
        yield 'a declared value the project changed is written back' => ['corrects-a-changed-value', $config];
        yield 'a missing step is inserted after the one declared before it' => ['inserts-a-missing-step-after-its-predecessor', $config];
        yield 'a missing job is added whole' => ['adds-a-missing-job', $config];
        yield 'a missing key of a job is added to it' => ['adds-a-missing-job-key', $config];
        yield 'a declared step the project edited is written back rather than duplicated' => ['writes-back-an-edited-declared-step', $config];
        yield 'a workflow synced as a managed block loses its markers and its steps gain their ids' => ['takes-over-the-managed-block', $config];
        yield 'a step without an id that holds a declared step is taken as that step and gains its id' => ['adopts-a-step-that-holds-the-declared-one', $config];
    }
}
