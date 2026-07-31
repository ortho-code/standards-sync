<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\General\ManagedBlock;

/** Draws and finds a single label's marker pair for one comment syntax. */
final readonly class MarkerGrammar
{
    public function __construct(
        private MarkerSyntax $syntax,
        private Label $label,
    ) {
    }

    public function open(): string
    {
        return sprintf('%s >>> %s (managed) >>>', $this->syntax->lead(), $this->label->value());
    }

    public function close(): string
    {
        return sprintf('%s <<< %s <<<', $this->syntax->lead(), $this->label->value());
    }

    /** Matches this label's whole block, markers included, across the lines between them. */
    public function blockPattern(): string
    {
        $lead = preg_quote($this->syntax->lead(), '#');
        $label = preg_quote($this->label->value(), '#');

        return sprintf('#^%s >>> %s \(managed\) >>>\R.*?\R%s <<< %s <<<[ \t]*$#ms', $lead, $label, $lead, $label);
    }
}
