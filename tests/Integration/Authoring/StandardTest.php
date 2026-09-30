<?php

declare(strict_types=1);

namespace Tests\OrthoCode\StandardsSync\Integration\Authoring;

use Acme\Tier\Tier;
use Composer\Autoload\ClassLoader;
use OrthoCode\StandardsSync\Authoring\Package;
use OrthoCode\StandardsSync\Authoring\Standard;
use OrthoCode\StandardsSync\Rules\PhpStan\IncludedRuleset\PhpStanIncludedRuleset;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Filesystem\Filesystem;
use Tests\OrthoCode\StandardsSync\Integration\IntegrationTestCase;

#[CoversClass(Standard::class)]
final class StandardTest extends IntegrationTestCase
{
    private const string SYMLINKED_PACKAGES = __DIR__ . '/fixtures/two-symlinked-packages';

    private ?ClassLoader $consumerLoader = null;

    #[After]
    protected function unregisterConsumerLoader(): void
    {
        $this->consumerLoader?->unregister();
    }

    public function testLocatesItsOwnPackageWhenNoneIsInjected(): void
    {
        // Defined in the engine repo, so self-location finds the root package: references render bare, directly under the project root.
        $standard = new class extends Standard {
            protected function enforce(Package $package): void
            {
                $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('phpstan.neon')));
            }
        };

        self::assertStringContainsString('includes "templates/phpstan.neon"', $standard->rules()[0]->description());
    }

    public function testAnIncludedTierSharesTheHandedDownPackage(): void
    {
        // Two tiers of one package, the shape an org uses to ship a library and an application standard on one release line.
        $standard = new class (new Package('/anywhere', 'vendor/acme/standards')) extends Standard {
            protected function enforce(Package $package): void
            {
                $this->include(new class ($package) extends Standard {
                    protected function enforce(Package $package): void
                    {
                        $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('shared/phpstan.neon')));
                    }
                });
                $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('package/phpstan.neon')));
            }
        };

        $rules = $standard->rules();

        self::assertStringContainsString('vendor/acme/standards/templates/shared/phpstan.neon', $rules[0]->description());
        self::assertStringContainsString('vendor/acme/standards/templates/package/phpstan.neon', $rules[1]->description());
    }

    public function testAnIncludedTierSelfLocatesWhenItIsNotHandedThePackage(): void
    {
        $standard = new class (new Package('/anywhere', 'vendor/acme/standards')) extends Standard {
            protected function enforce(Package $package): void
            {
                // Constructed without a package, so it locates its own — here the engine repo, since that is where the class is defined.
                $this->include(new class extends Standard {
                    protected function enforce(Package $package): void
                    {
                        $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('shared/phpstan.neon')));
                    }
                });
                $this->addRule(new PhpStanIncludedRuleset(ruleset: $package->path('package/phpstan.neon')));
            }
        };

        $rules = $standard->rules();

        // Both tiers read from one templates directory, yet they render against different roots: the reason a tier hands its package down.
        self::assertStringContainsString('includes "templates/shared/phpstan.neon"', $rules[0]->description());
        self::assertStringContainsString('vendor/acme/standards/templates/package/phpstan.neon', $rules[1]->description());
    }

    public function testTiersFromTwoSymlinkedPackagesEachLocateTheirOwnPackage(): void
    {
        // A second-tier package includes a base tier from another package, both installed through symlinked path repositories: each tier locates itself from a class file that resolves outside the consumer project.
        chdir($this->installThroughSymlinks([
            'acme/base' => 'Acme\Base\\',
            'acme/tier' => 'Acme\Tier\\',
        ]));

        $rules = new Tier()->rules();

        // The base tier reads its template from where the package physically is, and each tier renders the vendor path of its own package.
        self::assertStringContainsString('root = true', (string) $rules[0]->apply(null));
        self::assertStringContainsString('includes "vendor/acme/base/templates/base.neon"', $rules[1]->description());
        self::assertStringContainsString('includes "vendor/acme/tier/templates/tier.neon"', $rules[2]->description());
    }

    /**
     * Installs the fixture's packages into a workspace copy of its consumer the way a symlinked path repository does, and returns the consumer's directory.
     * The consumer carries composer's install record; only the links are made here, since a checkout without symlink support would break committed ones.
     * A class loader registered for the consumer's vendor directory is what makes composer's runtime API read the record.
     *
     * @param array<string, string> $namespaces package name => its PSR-4 namespace
     */
    private function installThroughSymlinks(array $namespaces): string
    {
        $filesystem = new Filesystem();
        $consumer = $this->workspace() . '/consumer';
        $filesystem->mirror(self::SYMLINKED_PACKAGES . '/consumer', $consumer);

        $this->consumerLoader = new ClassLoader($consumer . '/vendor');
        foreach ($namespaces as $name => $namespace) {
            $filesystem->symlink(self::SYMLINKED_PACKAGES . '/' . $name, $consumer . '/vendor/' . $name);
            $this->consumerLoader->addPsr4($namespace, $consumer . '/vendor/' . $name . '/src');
        }
        $this->consumerLoader->register();

        return $consumer;
    }
}
