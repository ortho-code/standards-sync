<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Formats\Yaml\Tree;

use RuntimeException;

/**
 * A YAML document whose root is a block mapping, its structure located by line and column over the document's own lines.
 * Nothing is re-printed: every value keeps its source text, and each is decoded from that text alone.
 */
final readonly class YamlTree
{
    private function __construct(
        private YamlLines $lines,
        private YamlMapping $root,
    ) {}

    /** @throws RuntimeException naming the line, for a construct the reader refuses rather than guesses at */
    public static function fromString(string $content): self
    {
        $lines = YamlLines::fromString($content);

        return new self($lines, new YamlTreeParser($lines)->root());
    }

    public function lines(): YamlLines
    {
        return $this->lines;
    }

    public function root(): YamlMapping
    {
        return $this->root;
    }

    /**
     * The value a path of mapping keys and sequence indexes leads to, or null where it leads nowhere; the root itself is not a value.
     *
     * @param list<string|int> $path
     */
    public function valueAt(array $path): ?YamlValue
    {
        $node = $this->root;
        $value = null;
        foreach ($path as $step) {
            $value = match (true) {
                is_int($step) && $node instanceof YamlSequence => ($node->items()[$step] ?? null)?->value(),
                is_string($step) && $node instanceof YamlMapping => $node->entry($step)?->value(),
                default => null,
            };
            if ($value === null) {
                return null;
            }
            $node = $value->node();
        }

        return $value;
    }

    /** @return array<array-key, mixed> */
    public function decoded(): array
    {
        return $this->root->decoded();
    }
}
