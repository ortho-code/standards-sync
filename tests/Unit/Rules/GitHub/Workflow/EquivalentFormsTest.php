<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\GitHub\Workflow;

use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\GitHubWorkflow;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\WorkflowContainment;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\WorkflowSyntax;
use OrthoCode\StandardsSync\Testing\FileContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** GitHub's equivalent spellings read as the declared ones, and what sync does where a spelling cannot hold the declared value. */
#[CoversClass(WorkflowSyntax::class)]
#[CoversClass(WorkflowContainment::class)]
final class EquivalentFormsTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function equivalents(): iterable
    {
        $triggers = FileContent::fromString(
            <<<'YAML'
                on:
                  pull_request:
                  push:
                YAML,
        );

        yield 'triggers as a list' => [$triggers, FileContent::fromString('on: [pull_request, push, workflow_dispatch]')];
        yield 'triggers as a mapping with an empty filter set' => [$triggers, FileContent::fromString(
            <<<'YAML'
                on:
                  pull_request: {}
                  push: ~
                YAML,
        )];
        yield 'a single trigger as a string' => [FileContent::fromString(
            <<<'YAML'
                on:
                  push:
                YAML,
        ), FileContent::fromString('on: push')];
        yield 'a job need as a list' => [self::job('needs: build'), self::job('needs: [build, lint]')];
        yield 'job needs as a string' => [self::job('needs: [build]'), self::job('needs: build')];
        yield 'runner labels as a list, a newer one and the project\'s own beside it' => [self::job('runs-on: ubuntu-26.04'), self::job('runs-on: [ubuntu-28.04, gpu]')];
        yield 'an environment as its name' => [self::job(
            <<<'YAML'
                environment:
                    name: production
                YAML,
        ), self::job('environment: production')];
        yield 'an environment name as a mapping with a url' => [self::job('environment: production'), self::job(
            <<<'YAML'
                environment:
                    name: production
                    url: https://acme.example
                YAML,
        )];
        yield 'a container as its image' => [self::job('container: php:8.5-cli'), self::job('container: {image: php:8.5-cli}')];
        yield 'a condition in an expression' => [self::job('if: github.event_name == \'push\''), self::job('if: ${{ github.event_name == \'push\' }}')];
        yield 'a filter pattern as a list' => [FileContent::fromString(
            <<<'YAML'
                on:
                  push:
                    branches: main
                YAML,
        ), FileContent::fromString(
            <<<'YAML'
                on:
                  push:
                    branches: [main, develop]
                YAML,
        )];
    }

    #[DataProvider('equivalents')]
    public function testReadsAnEquivalentSpellingAsTheDeclaredOne(string $declared, string $content): void
    {
        self::assertSame($content, self::rule($declared)->apply($content));
    }

    public function testAddsAMissingNeedToAList(): void
    {
        self::assertSame(self::job('needs: [lint, build]'), self::rule(self::job('needs: build'))->apply(self::job('needs: [lint]')));
    }

    public function testRefusesASingleValueWhereMoreAreDeclared(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('It has "jobs.checks.needs" as a single value where the standard declares a list; write it as a list and sync again.');

        self::rule(self::job('needs: [build, lint]'))->apply(self::job('needs: build'));
    }

    public function testRefusesTriggersAsAListWhereADeclaredOneIsFiltered(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('It has "on" as a list where the standard declares a mapping; write it as a mapping and sync again.');

        self::rule(FileContent::fromString(
            <<<'YAML'
                on:
                  push:
                    branches:
                      - main
                YAML,
        ))->apply(FileContent::fromString('on: [push]'));
    }

    public function testReplacesAnEnvironmentNameThatDiffersWithTheDeclaredMappingInTheProjectsIndentation(): void
    {
        self::assertSame(
            self::job(
                <<<'YAML'
                    environment:
                      name: production
                    YAML,
            ),
            self::rule(self::job(
                <<<'YAML'
                    environment:
                        name: production
                    YAML,
            ))->apply(self::job('environment: staging')),
        );
    }

    public function testGivesAValueSpelledAsNothingTheDeclaredOne(): void
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
            self::rule(FileContent::fromString(
                <<<'YAML'
                    on:
                      push:
                        branches:
                          - main
                    YAML,
            ))->apply(FileContent::fromString(
                <<<'YAML'
                    on:
                      push: ~
                    YAML,
            )),
        );
    }

    public function testReplacesARunnerListThatMeetsNoDeclaredLabel(): void
    {
        self::assertSame(self::job('runs-on: ubuntu-26.04'), self::rule(self::job('runs-on: ubuntu-26.04'))->apply(self::job('runs-on: [ubuntu-24.04, gpu]')));
    }

    private static function rule(string $declared): GitHubWorkflow
    {
        return new GitHubWorkflow(FileTarget::fromString('.github/workflows/checks.yml'), $declared);
    }

    /** A workflow of one job holding the given lines, indented under it. */
    private static function job(string $lines): string
    {
        $indented = implode("\n", array_map(static fn(string $line): string => $line === '' ? '' : '    ' . $line, explode("\n", $lines)));

        return FileContent::fromString("jobs:\n  checks:\n" . $indented);
    }
}
