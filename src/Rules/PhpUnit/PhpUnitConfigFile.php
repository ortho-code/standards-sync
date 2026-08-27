<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\PhpUnit;

use OrthoCode\StandardsSync\Core\Rule\FileTarget;
use OrthoCode\StandardsSync\Core\Text\Lines;

/**
 * The PHPUnit config file as a rule target, in PHPUnit's own lookup order — shared so no rule re-derives the precedence.
 * Also holds the family's shared config knowledge: the root element every rule edits, and the minimal skeleton for rules that create the config on absence.
 */
final readonly class PhpUnitConfigFile
{
    /** The one root element a phpunit config has; every rule in the family reads and writes its attributes. */
    public const string ROOT_ELEMENT = 'phpunit';

    private const array CANDIDATES = ['phpunit.xml', 'phpunit.dist.xml', 'phpunit.xml.dist'];

    public static function target(): FileTarget
    {
        return FileTarget::fromStrings(...self::CANDIDATES);
    }

    /**
     * The minimal skeleton for any phpunit rule that must create the config.
     * It names the bootstrap and one test-suite directory (vendor/autoload.php, tests — phpunit's own generated defaults) because nothing is schema-required but a suite-less config makes a bare phpunit run print usage and fail; the maintainer verifies the paths.
     */
    public static function createConfig(): string
    {
        return Lines::join([
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<phpunit bootstrap="vendor/autoload.php">',
            '    <testsuites>',
            '        <testsuite name="default">',
            '            <directory>tests</directory>',
            '        </testsuite>',
            '    </testsuites>',
            '</phpunit>',
            '',
        ]);
    }
}
