<?php

namespace Midi\Util;

use Midi\Container;
use Midi\Exception\Exception;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Helper\Helper;

class Util
{
    /**
     * Check port available.
     *
     * @param array $ports
     * @throws Exception
     */
    public static function checkPortsAvailable($ports)
    {
        foreach ($ports as $port) {
            $process = new Process('nc -z 127.0.0.1 ' . $port);
            $process->run();
            if ($process->isSuccessful()) {
                throw new Exception("<error>Port: " . trim($port) . " already in use!</error>");
            }
        }
    }

    public static function checkMacOSVersion()
    {
        $process = new Process('system_profiler SPSoftwareDataType');
        $process->run();
        $systemInfo = trim($process->getOutput());

        $version = '';
        $SIPStatus = '';
        $output = Container::make('output');

        // System Version: macOS 10.12.6 (16G29)
        $preg = '/System Version:\s+\w*\s(\d*\.\d*.\d*)/';
        preg_match($preg, $systemInfo, $matches);
        if ($matches) {
            $version = $matches[1];
        }

        // System Integrity Protection: Disabled
        $preg = '/System Integrity Protection:\s(\w+)/';
        preg_match($preg, $systemInfo, $matches);
        if ($matches) {
            $SIPStatus = $matches[1];
        }
        if (version_compare(substr($version, 0, 4), '10.12') > 0 && strtolower($SIPStatus) != 'disabled') {
            // version > 10.12, need check SIP
            $output->writeln("<info>System Version: $version</info>");
            $output->writeln("<info>System Integrity Protection: $SIPStatus</info>");
            throw new Exception("<error>When system Version > 10.12, need to Disabled SIP.</error>");
        }

        return true;
    }

    public static function getPrintString($data)
    {
        return preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data);
    }

    public static function buildSplitLine($message)
    {
        static $formatter;
        if ($formatter == null) {
            $output = Container::make('output');
            $formatter = $output->getFormatter();
        }
        return sprintf('<options=underscore;fg=black;bg=cyan>%s</>',
            str_repeat('=', Helper::strlenWithoutDecoration($formatter, $message)));
    }

    public static function getProgressBarObj($max)
    {
        $progressBar = new ProgressBar(Container::make('output'), $max);
        $progressBar->setBarCharacter('<info>></info>');
        $progressBar->setEmptyBarCharacter('-');
        $progressBar->setProgressCharacter('<info>></info>');
        $progressBar->setFormat('<info>Progressing:</info> %current%/%max% [%bar%] %percent:3s%% %memory:6s%');

        return $progressBar;
    }

    /**
     * @param bool|string $saveFile false not save, true save to default dir, or point a dir
     * @param array $sessions
     *
     * @throws Exception
     */
    public static function saveSessionToFile($saveFile, $sessions)
    {
        if ($saveFile === true) {
            // Is true save to default directory
            $sessionDir = Container::make('sessionDir');
        } else {
            $sessionDir = $saveFile;
            if (!is_dir($sessionDir)) {
                mkdir($sessionDir, 0755, true);
                if (!is_dir($sessionDir)) {
                    throw new Exception("<error>Create dir $sessionDir fail.</error>");
                }
            }
        }

        $output = Container::make('output');
        foreach ($sessions as $session) {
            $filename = $sessionDir . DR . $session['SessionId'] . '.json';
            file_put_contents($filename, json_encode($session));
            $output->writeln("<info>Save session file: <comment>$filename</comment></info>",
                OutputInterface::VERBOSITY_VERBOSE);
        }
    }
}
