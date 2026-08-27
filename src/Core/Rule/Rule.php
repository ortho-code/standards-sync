<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Core\Rule;

/**
 * The unifying primitive: one pure content transform against one target file.
 * Drift is derived, not declared: a rule is satisfied exactly when applying it changes nothing.
 */
interface Rule
{
    /** The file this rule transforms, as ordered path candidates. */
    public function target(): FileTarget;

    /**
     * Pure, idempotent transform from current content to desired content.
     * Null in: the file does not exist. Null out: the file should not exist.
     */
    public function apply(?string $content): ?string;

    /** One line stating what this rule enforces; feeds the drift report and the generated docs. */
    public function description(): string;
}
