<?php

/**
 * @author tanmingliang
 * @author fangjunda
 */

namespace DiPlugin\Util;

use DiPlugin\DiConfig;
use Midi\Container;
use Midi\Exception\Exception;
use Midi\Exception\RuntimeException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class PreKoalaCheck
{

    /**
     * Prepare internal CodeIgniter system code for project which use codeIgniter framework.
     *
     * @param bool $silent
     * @return bool
     * @throws RuntimeException
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    public static function prepareCISystem($silent = false)
    {
        /** @var \Midi\Config $config */
        $config = Container::make('config');
        $prepare = $config->get('php', 'prepare-ci-system');
        if ($prepare === false || $prepare === 0) {
            return true;
        }

        if (!DiConfig::isCIFramework()) {
            return true;
        }

        $ciSystemDir = $config->get('php', 'ci-system-path');
        if ($ciSystemDir === null) {
            // local file system exist `system` dir at the same dir of the project.
            $ciSystemDir = Container::make('ciSystemDir');
        }
        if (is_dir($ciSystemDir)) {
            return true;
        }

        // git clone internal ci system to local file system
        $gitUrl = $config->get('php', 'ci-system-git');
        if (empty($gitUrl)) {
            throw new RuntimeException('Can not find `ci-system-git` in your config.yml');
        }
        $process = new Process("git clone $gitUrl $ciSystemDir");
        $process->run();
        if (!$silent) {
            Container::make('output')->writeln("<info>git clone CodeIgniter's system to $ciSystemDir.</info>",
                OutputInterface::VERBOSITY_VERBOSE);
        }
        return true;
    }

    /**
     * @throws Exception
     */
    public static function checkProjectDir()
    {
        $cwd = Container::make('workingDir');

        $finder = new Finder();
        $count = $finder->in($cwd)->name('composer.json')->depth(0)->count();
        if ($count == 0) {
            return true; // maybe not use composer
        }

        if (!is_dir($cwd . '/vendor')) {
            throw new Exception("<error>Can not find vendor dir, should `composer install -o --no-dev` first.</error>");
        }

        $finder = new Finder();
        $count = $finder->in($cwd . '/vendor')->name('autoload.php')->depth(0)->count();
        if ($count == 0) {
            throw new Exception("<error>Can not find `vendor/autoload.php`, `composer install -o --no-dev` first.</error>");
        }

        return true;
    }

    public static function checkPhpExt($list = ['redis', 'memcached', 'apcu',])
    {
        $output = Container::make('output');
        foreach ($list as $ext) {
            if (!extension_loaded($ext)) {
                $output->writeln(<<<EOT
<info>Can not find <comment>$ext</comment> extension, without extension maybe not affect use.</info>
<info>For a better experience, recommended to install it.</info>

EOT
                );
            }
        }

        if (!DiConfig::isNuwaFramework()) {
            return ;
        }

        // check nuwa-yaf extension
        if (!extension_loaded('yaf')) {
            $output->writeln("<error>Nuwa framework need <comment>DIDI INTERNAL</comment> `yaf` extension.</error>");
            exit;
        }

        // check is internal yaf ?
        if (false === strpos(YAF_VERSION, 'Nuwa')) {
            $output->writeln("<error>Install wrong `yaf` extension, you should install <comment>DIDI INTERNAL</comment> `yaf` extension.</error>");
            exit;
        }
    }

    // check biz-config
    public static function checkBizConfig($throw = true)
    {
        $cwd = Container::make('workingDir');
        $bizConfigDir = Container::make('bizConfigDir');
        if (!is_dir($bizConfigDir)) {
            $msg = <<<EOT
<info>Midi can not find `biz-config` code at $bizConfigDir.</info>
<notice>If your project $cwd do not need biz-config, you could ignore this notice.</notice>

EOT;
            if ($throw) {
                throw new Exception($msg);
            } else {
                $output = Container::make('output');
                $output->writeln($msg);
            }
        }

        return true;
    }
}
