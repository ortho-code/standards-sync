<?php

declare(strict_types=1);

namespace AlleKnalle\StandardsSync\Presentation\Cli;

use AlleKnalle\StandardsSync\Core\Config\ConfigLoader;
use AlleKnalle\StandardsSync\Core\Engine\Engine;
use AlleKnalle\StandardsSync\Core\Filesystem\Path;
use AlleKnalle\StandardsSync\Infrastructure\Filesystem\SymfonyFilesystem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'sync', description: 'Sync managed config blocks into the configured roots.')]
final class SyncCommand extends Command
{
    private const string DEFAULT_CONFIG = 'standards-sync.php';

    protected function configure(): void
    {
        $this
            ->addOption('check', null, InputOption::VALUE_NONE, 'Report drift and exit non-zero without writing.')
            ->addOption('root', null, InputOption::VALUE_REQUIRED, 'Directory to run in; relative roots resolve against it (default: current directory).')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to the standards-sync.php config file.', self::DEFAULT_CONFIG);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $root = $input->getOption('root');
        if (is_string($root) && !chdir($root)) {
            $style->error(sprintf('Cannot change to root directory "%s".', $root));

            return Command::FAILURE;
        }

        $config = (new ConfigLoader())->loadFrom(Path::fromString((string) $input->getOption('config')));

        $engine = Engine::create(new SymfonyFilesystem());
        $plan = $engine->plan($config);

        (new DriftReport())->render($plan, $style);

        if ($input->getOption('check') === true) {
            return $plan->hasDrift() ? Command::FAILURE : Command::SUCCESS;
        }

        $engine->apply($plan);

        return Command::SUCCESS;
    }
}
