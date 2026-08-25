<?php

declare(strict_types=1);

namespace StandardsSync\Rules\PhpUnit\BaseConfig;

use StandardsSync\Core\Rule\FileTarget;
use StandardsSync\Core\Rule\Rule;
use StandardsSync\Rules\PhpUnit\PhpUnitConfigFile;
use InvalidArgumentException;

/**
 * Seeds a project that has no PHPUnit config with the org's template, verbatim, and never edits an existing config.
 * For a tool with no import tier the template is one-shot — only values that also have their own rule stay enforced: the template bootstraps, rules converge.
 * Declared first in a rule set it decides the created base for the whole family — later rules receive the template instead of the engine skeleton.
 */
final readonly class PhpUnitBaseConfig implements Rule
{
    public function __construct(private string $config)
    {
        if ($this->config === '') {
            throw new InvalidArgumentException('The base config template cannot be empty.');
        }
    }

    public function target(): FileTarget
    {
        return PhpUnitConfigFile::target();
    }

    public function apply(?string $content): ?string
    {
        return $content ?? $this->config;
    }

    public function description(): string
    {
        return 'Seeds a missing PHPUnit config with the org base config.';
    }
}
