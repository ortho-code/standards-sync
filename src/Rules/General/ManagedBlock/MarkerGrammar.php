<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Rules\General\ManagedBlock;

/** Draws and finds a single label's marker pair for one comment syntax. */
final readonly class MarkerGrammar
{
    private const string OPEN_LINE = '%s >>> %s - managed >>>';
    private const string CLOSE_LINE = '%s <<< %s <<<';

    public function __construct(
        private MarkerSyntax $syntax,
        private Label $label,
    ) {
    }

    public function open(): string
    {
        return sprintf(self::OPEN_LINE, $this->syntax->lead(), $this->label->value());
    }

    public function close(): string
    {
        return sprintf(self::CLOSE_LINE, $this->syntax->lead(), $this->label->value());
    }

    /** Matches this label's whole block, markers included, across the lines between them — built from the rendered marker lines, so drawing and finding can never drift apart. */
    public function blockPattern(): string
    {
        return sprintf('#^%s\R.*?\R%s[ \t]*$#ms', preg_quote($this->open(), '#'), preg_quote($this->close(), '#'));
    }
}
