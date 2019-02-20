<?php

/**
 * @author tanmingliang
 * @author fangjunda
 */

namespace DiPlugin\Command;

use Midi\Console\Application;
use Midi\Container;
use DiPlugin\Mock\MockDisf;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Stopwatch\Stopwatch;

class InitCommand extends Command
{
    protected function configure()
    {
        $this->setName('init')
            ->setDescription('Initialize midi, mock disf...')
            ->addOption('--module', '-m', InputOption::VALUE_OPTIONAL, "Module name")
            ->addOption('--increase', '-i', InputOption::VALUE_NONE, "Increase mode")
            ->setHelp('<info>php midi init</info>');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $workingDir = Container::make('workingDir');
            $module = $input->getOption('module');
            $increase = $input->getOption('increase');
            if ($increase) {
                $output->writeln("<info>Use increase mode</info>", OutputInterface::VERBOSITY_VERY_VERBOSE);
            }

            /** @var Stopwatch $stopWatch */
            $stopWatch = Container::make('stopWatch');
            $stopWatch->start('init');

            /** @var Application $app */
            $app = $this->getApplication();
            MockDisf::generate($app->getMidi()->getConfig(), $workingDir, $module, $increase);

            $output->writeln(sprintf("<info>Disf generate spent %d ms</info>",
                $stopWatch->stop('init')->getDuration()), OutputInterface::VERBOSITY_VERBOSE);
        } catch (\Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }
    }
}
