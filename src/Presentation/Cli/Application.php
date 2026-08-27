<?php

declare(strict_types=1);

namespace OrthoCode\StandardsSync\Presentation\Cli;

use OrthoCode\StandardsSync\Presentation\Cli\Command\SyncCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

/** The console application: the driving adapter that turns command-line input into a sync run. */
final class Application extends ConsoleApplication
{
    private const string NAME = 'standards-sync';

    public function __construct()
    {
        parent::__construct(self::NAME);

        $this->addCommand(new SyncCommand());
    }
}
