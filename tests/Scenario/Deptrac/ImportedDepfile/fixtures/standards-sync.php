<?php

declare(strict_types=1);

use AlleKnalle\StandardsSync\Core\Config\SyncConfig;
use AlleKnalle\StandardsSync\Core\RuleSet\ComposableRuleSet;
use AlleKnalle\StandardsSync\Rules\Deptrac\ImportedDepfile\DeptracImportedDepfile;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new DeptracImportedDepfile(depfile: 'vendor/acme/standards/deptrac.yaml'));
    }
});
