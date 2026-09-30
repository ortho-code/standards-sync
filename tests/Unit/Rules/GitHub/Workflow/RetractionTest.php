<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\GitHub\Workflow;

use InvalidArgumentException;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\DeclaredWorkflow;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\GitHubWorkflow;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\WorkflowContainment;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\WorkflowDifference;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** What the retraction scenarios cannot show: the pointers a workflow records, the templates it refuses, and the edges of taking a node out. */
#[CoversClass(GitHubWorkflow::class)]
#[CoversClass(DeclaredWorkflow::class)]
#[CoversClass(WorkflowContainment::class)]
#[CoversClass(WorkflowDifference::class)]
final class RetractionTest extends TestCase
{
    public function testRecordsEveryDeclaredNodeAsAPointer(): void
    {
        self::assertSame(
            ['/on', '/on/push', '/on/push/branches', '/on/push/branches/release~1**', '/jobs', '/jobs/checks', '/jobs/checks/needs', '/jobs/checks/needs/build', '/jobs/checks/steps', '/jobs/checks/steps/checks', '/jobs/checks/steps/checks/id', '/jobs/checks/steps/checks/run'],
            self::rule(FileContent::fromString(
                <<<'YAML'
                    on:
                      push:
                        branches: [release/**]
                    jobs:
                      checks:
                        needs: build
                        steps:
                          - id: checks
                            run: composer app-checks
                    YAML,
            ))->entries(),
        );
    }

    public function testRejectsATemplateWithAJobsKeyButNoJobs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The declared workflow has a jobs key but no jobs.');

        DeclaredWorkflow::fromString(FileContent::fromString('jobs:'));
    }

    public function testRejectsATemplateWithAStepsKeyButNoSteps(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The declared job "checks" has a steps key but no steps.');

        DeclaredWorkflow::fromString(FileContent::fromString(
            <<<'YAML'
                jobs:
                  checks:
                    steps: []
                YAML,
        ));
    }

    public function testRejectsATemplateWithAFilterOfNoPatterns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The declared event "push" has a branches filter with no patterns.');

        DeclaredWorkflow::fromString(FileContent::fromString(
            <<<'YAML'
                on:
                  push:
                    branches:
                YAML,
        ));
    }

    public function testTakesARetiredItemOutOfAFlowList(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    on:
                      push:
                        branches: [main, develop]
                    YAML,
            ),
            self::rule(self::pushOnMain())->withRetired(['/on/push/branches/master'])->apply(FileContent::fromString(
                <<<'YAML'
                    on:
                      push:
                        branches: [main, master, develop]
                    YAML,
            )),
        );
    }

    public function testGivesAStringWhoseOnlyItemIsRetiredTheDeclaredValue(): void
    {
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    on:
                      push:
                        branches:
                          - main
                    YAML,
            ),
            self::rule(self::pushOnMain())->withRetired(['/on/push/branches/master'])->apply(FileContent::fromString(
                <<<'YAML'
                    on:
                      push:
                        branches: master
                    YAML,
            )),
        );
    }

    public function testNamesTheRetiredItemOfAStringItReplaces(): void
    {
        self::assertSame(
            'It still has master in "on.push.branches", which the standard no longer declares, so "on.push.branches" takes the value it declares now.',
            self::rule(self::pushOnMain())->withRetired(['/on/push/branches/master'])->explain(FileContent::fromString(
                <<<'YAML'
                    on:
                      push:
                        branches: master
                    YAML,
            )),
        );
    }

    public function testGivesTriggersSpelledWithOnlyRetiredOnesTheDeclaredTriggers(): void
    {
        $rule = self::rule(self::pushOnMain())->withRetired(['/on/pull_request', '/on/merge_group']);

        self::assertSame(
            'It still has pull_request, merge_group in "on", which the standard no longer declares, so "on" takes the value it declares now.',
            $rule->explain(FileContent::fromString('on: [pull_request, merge_group]')),
        );
        self::assertSame(self::pushOnMain(), $rule->apply(FileContent::fromString('on: pull_request')));
    }

    public function testStillRefusesTriggersSpelledWithOneOfTheProjectsOwn(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('It has "on" as a list where the standard declares a mapping; write it as a mapping and sync again.');

        self::rule(self::pushOnMain())->withRetired(['/on/pull_request'])->apply(FileContent::fromString('on: [pull_request, workflow_dispatch]'));
    }

    public function testRefusesARetiredTriggerInsideBraces(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('It still has "on.pull_request", which the standard no longer declares, inside "on", which is written in brackets or braces and cannot be edited by sync; write "on" as an indented block and sync again.');

        self::rule(self::pushOnMain())->withRetired(['/on/pull_request'])->apply(FileContent::fromString('on: {push: {branches: [main]}, pull_request: {}}'));
    }

    public function testRefusesARetiredPatternNestedInsideBraces(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('It still has master in "on.push.branches", which the standard no longer declares, inside "on", which is written in brackets or braces and cannot be edited by sync; write "on" as an indented block and sync again.');

        self::rule(self::pushOnMain())->withRetired(['/on/push/branches/master'])->apply(FileContent::fromString('on: {push: {branches: [main, master]}}'));
    }

    public function testRefusesARetiredPatternInBracketsOverSeveralLines(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('It still has master in "on.push.branches", which the standard no longer declares, inside "on.push.branches", which is written in brackets or braces and cannot be edited by sync; write "on.push.branches" as an indented block and sync again.');

        self::rule(self::pushOnMain())->withRetired(['/on/push/branches/master'])->apply(FileContent::fromString(
            <<<'YAML'
                on:
                  push:
                    branches: [main,
                      master]
                YAML,
        ));
    }

    public function testGivesTriggersInBracesNamingOnlyRetiredOnesTheDeclaredTriggers(): void
    {
        self::assertSame(self::pushOnMain(), self::rule(self::pushOnMain())->withRetired(['/on/pull_request'])->apply(FileContent::fromString('on: {pull_request: {}}')));
    }

    public function testLeavesBracesWithoutTheRetiredNodeAlone(): void
    {
        $content = FileContent::fromString('on: {push: {branches: [main]}}');

        self::assertSame($content, self::rule(self::pushOnMain())->withRetired(['/on/push/branches/master', '/on/push/tags'])->apply($content));
    }

    public function testLeavesARetiredNodeTheProjectNoLongerHasAlone(): void
    {
        $content = FileContent::fromString(
            <<<'YAML'
                on:
                  push:
                    branches:
                      - main
                YAML,
        );

        self::assertSame($content, self::rule(self::pushOnMain())->withRetired(['/jobs/lowest', '/on/push/tags'])->apply($content));
    }

    public function testExplainsWhatItTakesOut(): void
    {
        self::assertSame(
            'It still has "on.push.tags", which the standard no longer declares. Its "on.push.branches" still lists master, which the standard no longer declares.',
            self::rule(self::pushOnMain())->withRetired(['/on/push/tags', '/on/push/tags/v*', '/on/push/branches/master'])->explain(FileContent::fromString(
                <<<'YAML'
                    on:
                      push:
                        branches:
                          - main
                          - master
                        tags:
                          - 'v*'
                    YAML,
            )),
        );
    }

    private static function pushOnMain(): string
    {
        return FileContent::fromString(
            <<<'YAML'
                on:
                  push:
                    branches:
                      - main
                YAML,
        );
    }

    private static function rule(string $declared): GitHubWorkflow
    {
        return new GitHubWorkflow(FileTarget::fromString('.github/workflows/checks.yml'), $declared);
    }
}
