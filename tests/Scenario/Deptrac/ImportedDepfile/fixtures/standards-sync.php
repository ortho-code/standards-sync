<?php

declare(strict_types=1);

use StandardsSync\Core\Config\SyncConfig;
use StandardsSync\Core\RuleSet\ComposableRuleSet;
use StandardsSync\Rules\Deptrac\ImportedDepfile\DeptracImportedDepfile;

return SyncConfig::create()->withRuleSet(new class extends ComposableRuleSet {
    public function __construct()
    {
        $this->addRule(new DeptracImportedDepfile(depfile: 'vendor/acme/standards/deptrac.yaml'));
    }
});
