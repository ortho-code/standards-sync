<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\GitHubWorkflow;
use OrthoCode\StandardsSync\Testing\FileContent;

// A framework standard declared beside the tier adds a trigger and a step to the tier's workflow, and asks for a newer checkout.
return SyncConfig::create()
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new GitHubWorkflow(
                target: FileTarget::fromString('.github/workflows/checks.yml'),
                workflow: FileContent::fromString(
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
                              - id: checks
                                run: composer app-checks
                        YAML,
                ),
            ));
        }
    })
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new GitHubWorkflow(
                target: FileTarget::fromString('.github/workflows/checks.yml'),
                workflow: FileContent::fromString(
                    <<<'YAML'
                        on:
                          pull_request:

                        jobs:
                          checks:
                            runs-on: ubuntu-24.04
                            steps:
                              - id: checkout
                                uses: actions/checkout@v6
                              # Builds the front end the checks read.
                              - id: assets
                                run: npm ci
                        YAML,
                ),
            ));
        }
    });
