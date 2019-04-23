<?php

namespace DiPlugin\Command;

use Midi\Util\Util;
use Midi\Util\OS;
use Midi\Command\ReplayerCommand;
use Midi\Command\BaseCommand;
use DiPlugin\Util\PreKoalaCheck;
use DiPlugin\Message;
use DiPlugin\DiConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

class DoctorCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('doctor')
            ->setDescription('doctor local environment and depends')
            ->setHelp('<info>php midi doctor</info>');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $output->writeln(Message::DOCTOR_COMMAND_WELCOME_INFO);
            ReplayerCommand::prepareReplayerSo(true);
            PreKoalaCheck::checkPhpExt();
            
            Util::throwIfPortsUsed($this->getMidi()->getKoala()->getPorts());

            if ($moduleName = DiConfig::getModuleName()) {
                // under module's dir
                PreKoalaCheck::checkProjectDir();
                PreKoalaCheck::checkBizConfig();
                PreKoalaCheck::prepareCISystem(true);
            } else {
                $output->writeln("<info>You should also `midi doctor` in your project dir.</info>");
            }

            if (OS::isMacOs()) {
                Util::checkMacOSVersion();
            }
        } catch (Exception $e) {
            $output->writeln($e->getMessage());
            $output->writeln("<info>" . Message::DOCTOR_COMMAND_MIDI_WIKI . "</info>");
            return;
        }

        $output->writeln("<info>Everything is OK. Just enjoy it!</info>");
    }
}
