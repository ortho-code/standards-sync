<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Psalm;

use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Text\Lines;
use AlleKnalle\StandardsSync\Rules\Psalm\LoosestErrorLevel\PsalmErrorLevel;

/**
 * The Psalm config file as a rule target, in Psalm's own lookup order — shared so no rule re-derives the precedence.
 * Also holds the family's shared config knowledge: the root element every rule edits, and the minimal skeleton for rules that create the config on absence.
 */
final readonly class PsalmConfigFile
{
    /** The one root element a psalm config has; every rule in the family reads and writes its attributes. */
    public const string ROOT_ELEMENT = 'psalm';

    private const array CANDIDATES = ['psalm.xml', 'psalm.xml.dist', 'psalm.dist.xml'];

    public static function target(): FileTarget
    {
        return FileTarget::fromStrings(...self::CANDIDATES);
    }

    /**
     * The minimal skeleton for any psalm rule that must create the config, carrying the given error level.
     * It names project paths (src, ignoring vendor — psalm's own init default) because the XSD requires <projectFiles> and an omitting skeleton would be a config psalm refuses to load; the maintainer verifies the paths.
     */
    public static function createConfig(PsalmErrorLevel $errorLevel): string
    {
        return Lines::join([
            '<?xml version="1.0"?>',
            sprintf('<psalm errorLevel="%d" xmlns="https://getpsalm.org/schema/config">', $errorLevel->value()),
            '    <projectFiles>',
            '        <directory name="src" />',
            '        <ignoreFiles>',
            '            <directory name="vendor" />',
            '        </ignoreFiles>',
            '    </projectFiles>',
            '</psalm>',
            '',
        ]);
    }
}
