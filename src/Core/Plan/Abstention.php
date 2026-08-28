<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Plan;

use OrthoCode\StandardsSync\Core\Filesystem\Path;
use OrthoCode\StandardsSync\Core\Rule\Rule;

/**
 * A file none of its rules had an opinion about: it does not exist, and no rule wanted to create it.
 * Nothing is written and nothing drifts, but the rules that stood down are kept so a declared rule never does nothing unseen.
 */
final readonly class Abstention
{
    /** @param non-empty-list<Rule> $rules */
    public function __construct(
        private Path $path,
        private array $rules,
    ) {}

    public function path(): Path
    {
        return $this->path;
    }

    /** @return non-empty-list<Rule> */
    public function rules(): array
    {
        return $this->rules;
    }
}
