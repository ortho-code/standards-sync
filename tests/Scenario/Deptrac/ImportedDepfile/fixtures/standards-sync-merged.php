<?php

declare(strict_types=1);

use OrthoCode\StandardsSync\Core\Config\SyncConfig;
use OrthoCode\StandardsSync\Core\RuleSet\ComposableRuleSet;
use OrthoCode\StandardsSync\Rules\Deptrac\ImportedDepfile\DeptracImportedDepfile;

// A framework standard declared beside the tier adds its own depfile to the tier's imports.
return SyncConfig::create()
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new DeptracImportedDepfile(depfile: 'vendor/acme/standards/deptrac.yaml'));
        }
    })
    ->withRuleSet(new class extends ComposableRuleSet {
        public function __construct()
        {
            $this->addRule(new DeptracImportedDepfile(depfile: 'vendor/acme/framework/deptrac.yaml'));
        }
    });
