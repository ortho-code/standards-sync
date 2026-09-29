<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Unit\Rules\Deptrac\ImportedDepfile;

use OrthoCode\StandardsSync\Rules\Deptrac\ImportedDepfile\DeptracImportedDepfile;
use OrthoCode\StandardsSync\Rules\PhpStan\IncludedRuleset\PhpStanIncludedRuleset;
use OrthoCode\StandardsSync\Testing\FileContent;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeptracImportedDepfile::class)]
final class DeptracImportedDepfileTest extends TestCase
{
    private const string IMPORT = 'vendor/acme/standards/deptrac.yaml';

    public function testMergedDeclarationsImportEveryDepfileOnceInDeclarationOrder(): void
    {
        $rule = $this->rule()
            ->withMerged(new DeptracImportedDepfile(depfile: 'vendor/acme/framework/deptrac.yaml'))
            ->withMerged($this->rule());

        self::assertSame([self::IMPORT, 'vendor/acme/framework/deptrac.yaml'], $rule->entries());
        self::assertSame('Ensures the deptrac config imports "vendor/acme/standards/deptrac.yaml", "vendor/acme/framework/deptrac.yaml".', $rule->description());
        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                      - vendor/acme/standards/deptrac.yaml
                      - vendor/acme/framework/deptrac.yaml
                    YAML,
            ),
            $rule->apply(null),
        );
    }

    public function testRefusesToMergeAnotherRule(): void
    {
        $this->expectException(LogicException::class);

        $this->rule()->withMerged(new PhpStanIncludedRuleset(ruleset: 'vendor/acme/standards/phpstan.neon'));
    }

    public function testAMissingDepfileTakesTheFirstRetiredImportsPlaceAndTheOthersAreRetracted(): void
    {
        $rule = $this->rule()->withRetired(['vendor/acme/standards/layers.yaml', 'vendor/acme/standards/strict.yaml']);
        $current = FileContent::fromString(
            <<<'YAML'
                imports:
                  - vendor/acme/standards/layers.yaml # the org layers
                  - local/architecture.yaml
                  - vendor/acme/standards/strict.yaml
                YAML,
        );

        self::assertSame(
            FileContent::fromString(
                <<<'YAML'
                    imports:
                      - vendor/acme/standards/deptrac.yaml # the org layers
                      - local/architecture.yaml
                    YAML,
            ),
            $rule->apply($current),
        );
        self::assertSame(
            'The deptrac config does not import "vendor/acme/standards/deptrac.yaml". It stops importing "vendor/acme/standards/layers.yaml", "vendor/acme/standards/strict.yaml", which no standard declares any more.',
            $rule->explain($current),
        );
    }

    private function rule(): DeptracImportedDepfile
    {
        return new DeptracImportedDepfile(depfile: self::IMPORT);
    }
}
