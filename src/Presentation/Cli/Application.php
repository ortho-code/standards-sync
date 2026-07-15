<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Presentation\Cli;

use Symfony\Component\Console\Application as ConsoleApplication;

/** The console application: the driving adapter that turns command-line input into a sync run. */
final class Application extends ConsoleApplication
{
    public function __construct()
    {
        parent::__construct('standards-sync');

        $this->addCommand(new SyncCommand());
    }
}
