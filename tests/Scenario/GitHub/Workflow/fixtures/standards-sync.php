<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\General\ManagedBlock\Label;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\GitHubWorkflow;
use OrthoCode\StandardsSync\Testing\FileContent;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new GitHubWorkflow(
            target: FileTarget::fromString('.github/workflows/checks.yml'),
            workflow: FileContent::fromString(
                <<<'YAML'
                    name: Checks

                    on:
                      pull_request:
                      push:
                        branches:
                          - main

                    jobs:
                      checks:
                        runs-on: ubuntu-26.04
                        steps:
                          - id: checkout
                            uses: actions/checkout@v7
                          # setup-php before the install
                          - id: setup-php
                            uses: shivammathur/setup-php@v2
                            with:
                              php-version: '8.5'
                              coverage: none
                          - id: install
                            uses: ramsey/composer-install@v4
                          - id: checks
                            run: composer app-checks
                    YAML,
            ),
            replacesBlock: Label::fromString('acme'),
        ));
    }
});
