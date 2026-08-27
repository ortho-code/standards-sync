<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\Ecs;

use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Text\Lines;
use OrthoCode\StandardsSync\Formats\Php\FluentChainWriter;
use RuntimeException;

/**
 * The ECS config file as a rule target: a single candidate, ecs.php in the project root — ECS looks nowhere else.
 * Also holds the family's shared config knowledge: the managed form is the fluent ECSConfig::configure() chain, and a created config is that chain holding a single call.
 */
final readonly class EcsConfigFile
{
    private const string CANDIDATE = 'ecs.php';

    private const string FLUENT_CHAIN = 'ECSConfig::configure()';

    public static function target(): FileTarget
    {
        return FileTarget::fromString(self::CANDIDATE);
    }

    /**
     * The rules manage only the fluent form; anything else is refused loudly rather than half-edited.
     * That covers ECS's deprecated callable style, and also a chain over an included builder (`$config = include …; return $config->…`): the chain is editable text, but it builds on a base the rule cannot see, so an edit could silently double-load a standard the include already carries.
     */
    public static function assertFluentChain(string $content): void
    {
        if (!str_contains($content, self::FLUENT_CHAIN)) {
            throw new RuntimeException(sprintf('The ECS config is not an %s chain; convert it to the fluent form so the config can be managed.', self::FLUENT_CHAIN));
        }
    }

    /**
     * A minimal fluent config holding just the given chained call, deliberately without withPaths(): the project maintainer owns their own parameters.
     * A multiline call passes its continuation lines pre-indented; the first line starts with `->` unindented.
     */
    public static function createConfig(string $call): string
    {
        return Lines::join([
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            'use Symplify\EasyCodingStandard\Config\ECSConfig;',
            '',
            'return ' . self::FLUENT_CHAIN,
            FluentChainWriter::INDENT . $call . ';',
            '',
        ]);
    }
}
