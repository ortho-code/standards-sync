<?php

declare(strict_types=1);

namespace StandardsSync\Presentation\Cli\Command;

use StandardsSync\Core\Config\ConfigLoader;
use StandardsSync\Core\Engine\Engine;
use StandardsSync\Core\Filesystem\Path;
use StandardsSync\Infrastructure\Filesystem\SymfonyFilesystem;
use StandardsSync\Presentation\Cli\Output\DriftReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: self::NAME, description: 'Sync managed config blocks into the configured roots.')]
final class SyncCommand extends Command
{
    private const string NAME = 'sync';
    private const string OPTION_CHECK = 'check';
    private const string OPTION_ROOT = 'root';
    private const string OPTION_CONFIG = 'config';
    private const string DEFAULT_CONFIG = 'standards-sync.php';

    protected function configure(): void
    {
        $this
            ->addOption(self::OPTION_CHECK, null, InputOption::VALUE_NONE, 'Report drift and exit non-zero without writing.')
            ->addOption(self::OPTION_ROOT, null, InputOption::VALUE_REQUIRED, 'Directory to run in; relative roots resolve against it (default: current directory).')
            ->addOption(self::OPTION_CONFIG, null, InputOption::VALUE_REQUIRED, 'Path to the standards-sync.php config file.', self::DEFAULT_CONFIG);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);

        $root = $input->getOption(self::OPTION_ROOT);
        if (is_string($root) && !chdir($root)) {
            $style->error(sprintf('Cannot change to root directory "%s".', $root));

            return Command::FAILURE;
        }

        $config = (new ConfigLoader())->loadFrom(Path::fromString((string) $input->getOption(self::OPTION_CONFIG)));

        $engine = new Engine(new SymfonyFilesystem());
        $plan = $engine->plan($config);

        (new DriftReport())->render($plan, $style);

        if ($input->getOption(self::OPTION_CHECK) === true) {
            return $plan->hasDrift() ? Command::FAILURE : Command::SUCCESS;
        }

        $engine->apply($plan);

        return Command::SUCCESS;
    }
}
