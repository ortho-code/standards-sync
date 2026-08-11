<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Rules\Composer\Requirement;

/** What a required package is needed for, named as composer names its two requirement sections. */
enum RequirementType: string
{
    case Runtime = 'require';
    case Development = 'require-dev';

    /**
     * Whether a package found under $found already covers this need.
     * A runtime requirement covers a development one — it is installed in both — while the reverse leaves the package missing in production.
     */
    public function isMetBy(self $found): bool
    {
        return $this === $found || $found === self::Runtime;
    }
}
