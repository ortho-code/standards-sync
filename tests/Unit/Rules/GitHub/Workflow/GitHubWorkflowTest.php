<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\GitHub\Workflow;

use InvalidArgumentException;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\Label;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\DeclaredWorkflow;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\GitHubWorkflow;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\WorkflowContainment;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\WorkflowDifference;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\WorkflowSyntax;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** What the scenarios cannot show: refusals, rejected declarations, explanations, and the edges of containment. */
#[CoversClass(GitHubWorkflow::class)]
#[CoversClass(DeclaredWorkflow::class)]
#[CoversClass(WorkflowContainment::class)]
#[CoversClass(WorkflowDifference::class)]
#[CoversClass(WorkflowSyntax::class)]
final class GitHubWorkflowTest extends TestCase
{
    public function testRefusesADeclaredStepMovedBeforeTheOneDeclaredBeforeIt(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The job "checks" runs the step "setup" before "checkout", which the standard declares first; move "setup" after "checkout" and sync again.');

        self::rule()->apply(FileContent::fromString(
            <<<'YAML'
                jobs:
                  checks:
                    steps:
                      - id: setup
                        uses: shivammathur/setup-php@v2
                      - id: checkout
                        uses: actions/checkout@v7
                YAML,
        ));
    }

    public function testRefusesAShapeSyncCannotEditIntoTheDeclaredOne(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('It has "jobs" as a list where the standard declares a mapping; write it as a mapping and sync again.');

        self::rule()->apply(FileContent::fromString('jobs: [checks]'));
    }

    public function testRejectsADeclaredStepWithoutAnId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The declared step 2 of the job "checks" has no id');

        DeclaredWorkflow::fromString(FileContent::fromString(
            <<<'YAML'
                jobs:
                  checks:
                    steps:
                      - id: checkout
                        uses: actions/checkout@v7
                      - run: composer app-checks
                YAML,
        ));
    }

    public function testRejectsAStepIdItsJobDeclaresTwice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The job "checks" declares the step id "checks" twice.');

        DeclaredWorkflow::fromString(FileContent::fromString(
            <<<'YAML'
                jobs:
                  checks:
                    steps:
                      - id: checks
                        run: a
                      - id: checks
                        run: b
                YAML,
        ));
    }

    public function testRejectsAWorkflowTheReaderRefuses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The declared workflow cannot be read: Line 1 holds an anchor');

        DeclaredWorkflow::fromString(FileContent::fromString('name: &name Checks'));
    }

    public function testCreatesAnAbsentWorkflowAsDeclared(): void
    {
        self::assertSame(self::declared(), self::rule()->apply(null));
    }

    public function testAKeyDeclaredWithNothingUnderItAsksOnlyForTheKey(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                on:
                  pull_request:
                    types: [opened]
                jobs:
                  checks:
                    steps:
                      - id: checkout
                        uses: actions/checkout@v7
                      - id: setup
                        uses: shivammathur/setup-php@v2
                YAML,
        );

        self::assertSame($content, self::rule()->apply($content));
    }

    public function testAValueTheProjectLeftEmptyTakesTheDeclaredOne(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                    jobs:
                      checks:
                        steps:
                          - id: checkout
                            uses: actions/checkout@v7
                          - id: setup
                            uses: shivammathur/setup-php@v2
                    YAML,
            ),
            self::rule()->apply(FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                    jobs:
                      checks:
                        steps:
                    YAML,
            )),
        );
    }

    public function testADeclaredListOfScalarsGainsTheItemsItLacks(): void
    {
        $rule = new GitHubWorkflow(FileTarget::fromString('checks.yml'), FileContent::fromString(
            <<<'YAML'
                on:
                  push:
                    branches:
                      - main
                      - master
                YAML,
        ));

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    on:
                      push:
                        branches: [develop, main, master]
                    YAML,
            ),
            $rule->apply(FileContent::fromString(
                <<<'YAML'
                    on:
                      push:
                        branches: [develop, main]
                    YAML,
            )),
        );
    }

    public function testExplainsEveryEditOnTheWay(): void
    {
        self::assertSame(
            'It still carries the markers of the managed block "acme", which this rule takes over. It does not have "on" yet. A step of the job "checks" is the declared step "checkout" without its id, and gains it. The job "checks" does not run the step "setup" yet; it goes after "checkout".',
            self::rule()->explain(FileContent::fromString(
                <<<'YAML'
                    # >>> acme - managed >>>
                    jobs:
                      checks:
                        steps:
                          - uses: actions/checkout@v7
                    # <<< acme <<<
                    YAML,
            )),
        );
    }

    public function testASecondSyncChangesNothing(): void
    {
        $once = (string) self::rule()->apply(FileContent::fromString(
            <<<'YAML'
                # >>> acme - managed >>>
                jobs:
                  checks:
                    steps:
                      - uses: actions/checkout@v7
                # <<< acme <<<
                YAML,
        ));

        self::assertSame($once, self::rule()->apply($once));
    }

    public function testAdoptsTheStepHoldingTheDeclaredOneAsItIsBeforeOneHoldingItOnAnOlderAction(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                    jobs:
                      checks:
                        steps:
                          - id: checkout
                            uses: actions/checkout@v7
                          - uses: shivammathur/setup-php@v1
                            with:
                              tools: phpcs
                          - uses: shivammathur/setup-php@v2
                            id: setup
                    YAML,
            ),
            self::rule()->apply(FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                    jobs:
                      checks:
                        steps:
                          - id: checkout
                            uses: actions/checkout@v7
                          - uses: shivammathur/setup-php@v1
                            with:
                              tools: phpcs
                          - uses: shivammathur/setup-php@v2
                    YAML,
            )),
        );
    }

    public function testAdoptsAStepOnAnOlderActionAndRaisesIt(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                    jobs:
                      checks:
                        steps:
                          - id: checkout
                            uses: actions/checkout@v7
                          - uses: shivammathur/setup-php@v2
                            with:
                              extensions: intl
                            id: setup
                          - uses: shivammathur/setup-php@v1
                    YAML,
            ),
            self::rule()->apply(FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                    jobs:
                      checks:
                        steps:
                          - id: checkout
                            uses: actions/checkout@v7
                          - uses: shivammathur/setup-php@v1
                            with:
                              extensions: intl
                          - uses: shivammathur/setup-php@v1
                    YAML,
            )),
        );
    }

    public function testRefusesWhenTheOnlyStepToAdoptRunsBeforeTheOneDeclaredBeforeIt(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The job "checks" runs the step "setup" before "checkout", which the standard declares first');

        self::rule()->apply(FileContent::fromString(
            <<<'YAML'
                on:
                  pull_request:
                jobs:
                  checks:
                    steps:
                      - uses: shivammathur/setup-php@v1
                      - id: checkout
                        uses: actions/checkout@v7
                YAML,
        ));
    }

    public function testReplacesABranchRefAndDropsADigestPinsCommentWithIt(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                    jobs:
                      checks:
                        steps:
                          - id: checkout
                            uses: actions/checkout@v7 # the project's own note
                          - id: setup
                            uses: shivammathur/setup-php@v2
                    YAML,
            ),
            self::rule()->apply(FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                    jobs:
                      checks:
                        steps:
                          - id: checkout
                            uses: actions/checkout@main # the project's own note
                          - id: setup
                            uses: shivammathur/setup-php@0123456789abcdef0123456789abcdef01234567 # v1.9.0
                    YAML,
            )),
        );
    }

    public function testWritesADeclaredDigestPinWithItsComment(): void
    {
        $rule = new GitHubWorkflow(FileTarget::fromString('checks.yml'), FileContent::fromString(
            <<<'YAML'
                jobs:
                  checks:
                    steps:
                      - id: checkout
                        uses: actions/checkout@0123456789abcdef0123456789abcdef01234567 # v7.0.1
                YAML,
        ));

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    jobs:
                      checks:
                        steps:
                          - id: checkout
                            uses: actions/checkout@0123456789abcdef0123456789abcdef01234567 # v7.0.1
                    YAML,
            ),
            $rule->apply(FileContent::fromString(
                <<<'YAML'
                    jobs:
                      checks:
                        steps:
                          - id: checkout
                            uses: actions/checkout@v6
                    YAML,
            )),
        );
    }

    public function testExplainsAVersionBelowTheMinimumAndOneThatNamesNone(): void
    {
        self::assertSame(
            'It has "jobs.checks.steps[checkout].uses" as actions/checkout@v6, below the declared actions/checkout@v7. It has "jobs.checks.steps[setup].uses" as shivammathur/setup-php@main, which names no version to compare with the declared shivammathur/setup-php@v2.',
            self::rule()->explain(FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                    jobs:
                      checks:
                        steps:
                          - id: checkout
                            uses: actions/checkout@v6
                          - id: setup
                            uses: shivammathur/setup-php@main
                    YAML,
            )),
        );
    }

    private static function rule(): GitHubWorkflow
    {
        return new GitHubWorkflow(FileTarget::fromString('.github/workflows/checks.yml'), self::declared(), Label::fromString('acme'));
    }

    private static function declared(): string
    {
        return FileContent::fromString(
            <<<'YAML'
                on:
                  pull_request:
                jobs:
                  checks:
                    steps:
                      - id: checkout
                        uses: actions/checkout@v7
                      - id: setup
                        uses: shivammathur/setup-php@v2
                YAML,
        );
    }
}
