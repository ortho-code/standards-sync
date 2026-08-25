<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Rector;

use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Text\Lines;
use AlleKnalle\StandardsSync\Formats\Php\FluentChainWriter;
use RuntimeException;

/**
 * The Rector config file as a rule target, in Rector's own lookup order — shared so no rule re-derives the precedence.
 * Also holds the family's shared config knowledge: the managed form is the fluent RectorConfig::configure() chain, and a created config is that chain holding a single call.
 */
final readonly class RectorConfigFile
{
    private const array CANDIDATES = ['rector.php', 'rector.dist.php'];

    private const string FLUENT_CHAIN = 'RectorConfig::configure()';

    public static function target(): FileTarget
    {
        return FileTarget::fromStrings(...self::CANDIDATES);
    }

    /** The rules manage only the fluent form; a callable-style config is refused loudly rather than half-edited. */
    public static function assertFluentChain(string $content): void
    {
        if (!str_contains($content, self::FLUENT_CHAIN)) {
            throw new RuntimeException(sprintf('The Rector config is not a %s chain; convert it to the fluent form so the config can be managed.', self::FLUENT_CHAIN));
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
            'use Rector\Config\RectorConfig;',
            '',
            'return ' . self::FLUENT_CHAIN,
            FluentChainWriter::INDENT . $call . ';',
            '',
        ]);
    }
}
