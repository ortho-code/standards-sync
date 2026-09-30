<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\GitHub\Workflow\GitHubWorkflow;
use OrthoCode\StandardsSync\Testing\FileContent;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new GitHubWorkflow(
            target: FileTarget::fromString('.github/workflows/deploy.yml'),
            workflow: FileContent::fromString(
                <<<'YAML'
                    on:
                      pull_request:
                      push:

                    jobs:
                      deploy:
                        needs: build
                        if: github.event_name == 'push'
                        runs-on: ubuntu-26.04
                        environment: production
                        steps:
                          - id: deploy
                            run: make deploy
                    YAML,
            ),
        ));
    }
});
