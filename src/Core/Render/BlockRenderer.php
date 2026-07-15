<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Core\Render;

use AlleKnalle\StandardsSync\Core\Block\MarkerGrammar;
use AlleKnalle\StandardsSync\Core\Block\MarkerSyntax;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Core\Plan\DesiredFile;
use AlleKnalle\StandardsSync\Core\Plan\ManagedBlock;

/**
 * The default renderer: marker-delimited managed blocks, usable for any comment-friendly file.
 * Each block is applied in turn to the running content, so several labels can share one file.
 */
final readonly class BlockRenderer implements FileRenderer
{
    /** Comment lead per file extension; anything unlisted uses a hash comment. */
    private const array SYNTAX_BY_EXTENSION = [
        'js' => MarkerSyntax::DoubleSlash,
        'mjs' => MarkerSyntax::DoubleSlash,
        'cjs' => MarkerSyntax::DoubleSlash,
        'ts' => MarkerSyntax::DoubleSlash,
        'ini' => MarkerSyntax::Semicolon,
    ];

    public function supports(DesiredFile $file): bool
    {
        return true;
    }

    public function render(DesiredFile $file, ?string $current): string
    {
        $syntax = $this->syntaxFor($file->path());

        $content = $current;
        foreach ($file->blocks() as $block) {
            $content = $this->applyBlock($block, $syntax, $content);
        }

        return $content ?? '';
    }

    private function applyBlock(ManagedBlock $block, MarkerSyntax $syntax, ?string $current): string
    {
        $grammar = new MarkerGrammar($syntax, $block->label());
        $rendered = $this->wrap($grammar, $block->content());

        // A missing file becomes just the block.
        if ($current === null) {
            return $rendered . "\n";
        }

        // An existing block is replaced in place, leaving everything around it untouched.
        if ($this->hasBlock($grammar, $current)) {
            return (string) preg_replace_callback($grammar->blockPattern(), static fn (): string => $rendered, $current);
        }

        // An existing file without the block keeps its content and gains the block at the end.
        return rtrim($current, "\n") . "\n\n" . $rendered . "\n";
    }

    private function wrap(MarkerGrammar $grammar, string $content): string
    {
        return $grammar->open() . "\n" . rtrim($content, "\n") . "\n" . $grammar->close();
    }

    private function hasBlock(MarkerGrammar $grammar, string $content): bool
    {
        return preg_match($grammar->blockPattern(), $content) === 1;
    }

    // The comment syntax is a fact about the file type, so the renderer derives it rather than the config author declaring it.
    private function syntaxFor(Path $path): MarkerSyntax
    {
        $extension = strtolower(pathinfo($path->value(), PATHINFO_EXTENSION));

        return self::SYNTAX_BY_EXTENSION[$extension] ?? MarkerSyntax::Hash;
    }
}
