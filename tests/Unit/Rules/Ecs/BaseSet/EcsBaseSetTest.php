<?php

declare(strict_types=1);

namespace Tests\AlleKnalle\StandardsSync\Unit\Rules\Ecs\BaseSet;

use AlleKnalle\StandardsSync\Rules\Ecs\BaseSet\EcsBaseSet;
use AlleKnalle\StandardsSync\Testing\FileContent;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(EcsBaseSet::class)]
final class EcsBaseSetTest extends TestCase
{
    private const string IMPORT = 'vendor/acme/standards/config/ecs.php';

    #[DataProvider('importScenarios')]
    public function testAppliesTheImport(?string $current, string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($current));
    }

    // The expected output is apply(current), so applying again to it must return it unchanged.
    #[DataProvider('importScenarios')]
    public function testApplyIsIdempotent(?string $current, string $expected): void
    {
        self::assertSame($expected, $this->rule()->apply($expected));
    }

    /** @return iterable<string, array{string|null, string}> */
    public static function importScenarios(): iterable
    {
        yield 'a project without a config gets one created, holding just the import' => [
            null,
            FileContent::fromString(
                <<<'PHP'
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
    ]);
PHP
            ),
        ];

        yield 'an existing withSets array gains the entry after its last entry' => [
            FileContent::fromString(
                <<<'PHP'
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSets([
        SetList::PSR_12,
    ]);
PHP
            ),
            FileContent::fromString(
                <<<'PHP'
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withSets([
        SetList::PSR_12,
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
    ]);
PHP
            ),
        ];

        yield 'a chain without withSets gains the call at its end' => [
            FileContent::fromString(
                <<<'PHP'
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPreparedSets(psr12: true);
PHP
            ),
            FileContent::fromString(
                <<<'PHP'
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
    ])
    ->withPreparedSets(psr12: true)
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
    ]);
PHP
            ),
        ];

        yield 'an already-registered import leaves the config unchanged' => [
            FileContent::fromString(
                <<<'PHP'
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
    ]);
PHP
            ),
            FileContent::fromString(
                <<<'PHP'
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([
        __DIR__ . '/vendor/acme/standards/config/ecs.php',
    ]);
PHP
            ),
        ];

        yield 'the inserted entry copies the indentation of a two-space file' => [
            FileContent::fromString(
                <<<'PHP'
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
  ->withSets([
    SetList::PSR_12,
  ]);
PHP
            ),
            FileContent::fromString(
                <<<'PHP'
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return ECSConfig::configure()
  ->withSets([
    SetList::PSR_12,
    __DIR__ . '/vendor/acme/standards/config/ecs.php',
  ]);
PHP
            ),
        ];
    }

    public function testRefusesACallableStyleConfig(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fluent form');

        $this->rule()->apply(FileContent::fromString(
            <<<'PHP'
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->sets([__DIR__ . '/config/sets.php']);
};
PHP
        ));
    }

    // The include-chain form is a valid fluent chain over a base the rule cannot see; editing it could double-load a standard the include already carries.
    public function testRefusesAnIncludeChainConfig(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fluent form');

        $this->rule()->apply(FileContent::fromString(
            <<<'PHP'
<?php

declare(strict_types=1);

$config = include '../../tools/easy-coding-standard/base-ruleset.php';

return $config
    ->withPaths(['src', 'tests']);
PHP
        ));
    }

    public function testRefusesASingleLineSetsArray(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('one entry per line');

        $this->rule()->apply(FileContent::fromString("return ECSConfig::configure()->withSets([SetList::PSR_12]);"));
    }

    public function testRejectsAnEmptySetPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EcsBaseSet(set: ' ');
    }

    // The author passes data, not PHP: expression text would be written verbatim into every consumer config.
    public function testRejectsAnExpressionAsTheSetPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('relative path');

        new EcsBaseSet(set: "__DIR__ . '/vendor/acme/standards/config/ecs.php'");
    }

    public function testRejectsAnAbsoluteSetPath(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('relative');

        new EcsBaseSet(set: '/vendor/acme/standards/config/ecs.php');
    }

    public function testTargetsTheSingleEcsConfigCandidate(): void
    {
        self::assertSame('ecs.php', $this->rule()->target()->toString());
    }

    public function testDescribesAndExplainsTheImport(): void
    {
        $rule = $this->rule();

        self::assertSame('Ensures the ECS config registers vendor/acme/standards/config/ecs.php in withSets().', $rule->description());
        self::assertSame('There is no ECS config yet; one is created registering vendor/acme/standards/config/ecs.php.', $rule->explain(null));
        self::assertSame('The ECS config does not register vendor/acme/standards/config/ecs.php in withSets().', $rule->explain(FileContent::fromString('return ECSConfig::configure();')));
    }

    private function rule(): EcsBaseSet
    {
        return new EcsBaseSet(set: self::IMPORT);
    }
}
