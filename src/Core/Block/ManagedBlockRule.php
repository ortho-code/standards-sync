<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Block;

use AlleKnalle\StandardsSync\Core\Rule\FileTarget;
use AlleKnalle\StandardsSync\Core\Rule\Rule;
use InvalidArgumentException;

/**
 * Places a template as a managed marker block.
 * An absent file becomes just the block; an existing same-label block is replaced in place, whichever rule produced it; a file without the block gains it at the end.
 */
final readonly class ManagedBlockRule implements Rule
{
    private MarkerSyntax $syntax;

    public function __construct(
        private FileTarget $target,
        private Label $label,
        private string $content,
    ) {
        $this->syntax = $this->agreedSyntax($target);
    }

    public function target(): FileTarget
    {
        return $this->target;
    }

    public function apply(?string $content): ?string
    {
        $grammar = new MarkerGrammar($this->syntax, $this->label);
        $rendered = $this->wrap($grammar, $this->content);

        // A missing file becomes just the block.
        if ($content === null) {
            return $rendered . "\n";
        }

        // An existing block is replaced in place, leaving everything around it untouched.
        if (preg_match($grammar->blockPattern(), $content) === 1) {
            return (string) preg_replace_callback($grammar->blockPattern(), static fn (): string => $rendered, $content);
        }

        // An existing file without the block keeps its content and gains the block at the end.
        return rtrim($content, "\n") . "\n\n" . $rendered . "\n";
    }

    public function description(): string
    {
        return sprintf('Places the managed "%s" block in %s.', $this->label->value(), $this->target->toString());
    }

    private function wrap(MarkerGrammar $grammar, string $content): string
    {
        return $grammar->open() . "\n" . rtrim($content, "\n") . "\n" . $grammar->close();
    }

    /**
     * Candidates name the same file under different filenames, so they must derive one shared comment syntax; a mixed list is refused as an authoring error.
     * Debatable and deliberately strict: apply() cannot know which candidate resolved (rules are pure), so per-candidate syntax is inexpressible, and refusing loudly beats silently guessing the first candidate.
     * A real same-format case that needs differing syntaxes justifies a per-candidate contract seam instead — the trade-off is recorded in docs/rule-model.md.
     */
    private function agreedSyntax(FileTarget $target): MarkerSyntax
    {
        $candidates = $target->candidates();
        $syntax = MarkerSyntax::fromPath($candidates[0]);
        foreach ($candidates as $candidate) {
            if (MarkerSyntax::fromPath($candidate) !== $syntax) {
                throw new InvalidArgumentException(sprintf(
                    'The candidates "%s" disagree on comment syntax; a managed block needs one syntax across all candidates.',
                    $target->toString(),
                ));
            }
        }

        return $syntax;
    }
}
