<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Documentation\Source;

use OrthoCode\StandardsSync\Core\Rule\Rule;
use ReflectionClass;
use RuntimeException;

/** The shipped rule classes found under the rule-library directory, resolved through its PSR-4 namespace prefix; supporting classes in the rule folders are not rules and stay out. */
final readonly class RuleLibrary
{
    /** @param list<class-string<Rule>> $ruleClasses */
    private function __construct(private array $ruleClasses) {}

    public static function fromDirectory(string $directory, string $namespacePrefix): self
    {
        $classes = [];
        foreach (Psr4Classes::fromDirectory($directory, $namespacePrefix)->all() as $class => $path) {
            if (!class_exists($class)) {
                if (!interface_exists($class) && !trait_exists($class)) {
                    throw new RuntimeException(sprintf('"%s" does not resolve to a loadable type ("%s").', $path, $class));
                }
                continue;
            }
            $reflection = new ReflectionClass($class);
            if (is_a($class, Rule::class, allow_string: true) && $reflection->isInstantiable()) {
                $classes[] = $class;
            }
        }
        sort($classes);

        return new self($classes);
    }

    /** @return list<class-string<Rule>> */
    public function ruleClasses(): array
    {
        return $this->ruleClasses;
    }
}
