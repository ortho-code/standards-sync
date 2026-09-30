<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\GitHub\Workflow;

use LogicException;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\Label;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\GitHubWorkflow;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** What the merge scenario cannot show: the declaration two workflows combine into, and the ones that cannot combine. */
#[CoversClass(GitHubWorkflow::class)]
final class MergeTest extends TestCase
{
    public function testWritesTheLaterDeclarationHoldingTheEarlierOneForAProjectWithoutTheFile(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                      push:
                        branches: [main]

                    jobs:
                      checks:
                        runs-on: ubuntu-24.04
                        steps:
                          - id: checkout
                            uses: actions/checkout@v6
                          - id: setup-php
                            uses: shivammathur/setup-php@v2
                            with:
                              php-version: '8.4'
                              tools: composer
                          # Runs the standard's checks.
                          - id: checks
                            run: composer app-checks
                          - id: assets
                            run: npm ci
                    YAML,
            ),
            self::earlier()->withMerged(self::later())->apply(null),
        );
    }

    public function testRecordsTheNodesOfBothDeclarations(): void
    {
        $entries = self::earlier()->withMerged(self::later())->entries();

        self::assertContains('/on/push/branches/main', $entries);
        self::assertContains('/on/pull_request', $entries);
        self::assertContains('/jobs/checks/steps/checks/run', $entries);
        self::assertContains('/jobs/checks/steps/assets/run', $entries);
        self::assertContains('/jobs/checks/steps/setup-php/with/tools', $entries);
        self::assertSame(array_values(array_unique($entries)), $entries);
    }

    public function testRefusesDeclarationsThatOrderTheirStepsTheOtherWay(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Two declarations of the workflow .github/workflows/checks.yml cannot be combined, since the later one cannot be made to hold the earlier: The job "checks" runs the step "checkout" before "checks", which the standard declares first; move "checkout" after "checks" and sync again.');

        self::rule(
            <<<'YAML'
                jobs:
                  checks:
                    steps:
                      - id: checks
                        run: composer app-checks
                      - id: checkout
                        uses: actions/checkout@v5
                YAML,
        )->withMerged(self::rule(
            <<<'YAML'
                jobs:
                  checks:
                    steps:
                      - id: checkout
                        uses: actions/checkout@v5
                      - id: checks
                        run: composer app-checks
                YAML,
        ));
    }

    public function testTakesOverTheManagedBlocksOfBothDeclarations(): void
    {
        $workflow = FileContent::fromString(
            <<<'YAML'
                jobs:
                  checks:
                    steps:
                      - id: checks
                        run: composer app-checks
                YAML,
        );
        $merged = new GitHubWorkflow(FileTarget::fromString('.github/workflows/checks.yml'), $workflow, Label::fromString('acme'))
            ->withMerged(new GitHubWorkflow(FileTarget::fromString('.github/workflows/checks.yml'), $workflow, Label::fromString('acme-framework')));

        self::assertSame($workflow, $merged->apply(FileContent::fromString(
            <<<'YAML'
                # >>> acme - managed >>>
                # >>> acme-framework - managed >>>
                jobs:
                  checks:
                    steps:
                      - id: checks
                        run: composer app-checks
                # <<< acme-framework <<<
                # <<< acme <<<
                YAML,
        )));
    }

    public function testMergesOnlyADeclarationOfTheSameFile(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('Only declarations of the workflow .github/workflows/checks.yml merge into it.');

        self::earlier()->withMerged(new GitHubWorkflow(FileTarget::fromString('.github/workflows/deploy.yml'), self::later()->apply(null) ?? ''));
    }

    private static function earlier(): GitHubWorkflow
    {
        return self::rule(
            <<<'YAML'
                on:
                  push:
                    branches: [main]

                jobs:
                  checks:
                    runs-on: ubuntu-24.04
                    steps:
                      - id: checkout
                        uses: actions/checkout@v5
                      - id: setup-php
                        uses: shivammathur/setup-php@v2
                        with:
                          php-version: '8.4'
                      # Runs the standard's checks.
                      - id: checks
                        run: composer app-checks
                YAML,
        );
    }

    private static function later(): GitHubWorkflow
    {
        return self::rule(
            <<<'YAML'
                on:
                  pull_request:

                jobs:
                  checks:
                    runs-on: ubuntu-24.04
                    steps:
                      - id: checkout
                        uses: actions/checkout@v6
                      - id: setup-php
                        uses: shivammathur/setup-php@v2
                        with:
                          php-version: '8.5'
                          tools: composer
                      - id: assets
                        run: npm ci
                YAML,
        );
    }

    private static function rule(string $workflow): GitHubWorkflow
    {
        return new GitHubWorkflow(FileTarget::fromString('.github/workflows/checks.yml'), FileContent::fromString($workflow));
    }
}
