<?php declare(strict_types=1);

/**
 * @author fangjunda
 */

namespace Midi\Command;

use Exception;
use GuzzleHttp\Client;
use Midi\Console\Application;
use Midi\Container;
use Midi\Util\FileUtil;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends Command
{
    const CMD = 'update';

    const URL_VERSION_TXT_UNSTABLE = 'https://github.com/didi/rdebug/raw/master/php/midi/version-dev.txt';
    const URL_MIDI_UNSTABLE = 'https://github.com/didi/rdebug/raw/master/output/bin/midi-dev.phar';
    const URL_VERSION_TXT_STABLE = 'https://github.com/didi/rdebug/raw/master/php/midi/version.txt';
    const URL_MIDI_STABLE = 'https://github.com/didi/rdebug/raw/master/output/bin/midi.phar';

    /**
     * path to res dir in the phar `midi`
     *
     * @var string
     */
    protected $res = null;

    /**
     * path to current version.txt in phar `midi`
     *
     * @var string
     */
    protected $currentVersionTxt = null;

    /**
     * path to temp update dir
     *
     * @var string
     */
    protected $upgradeDir = null;

    /**
     * path to the download midi.phar
     *
     * @var string
     */
    protected $newMidiPath = null;

    /**
     * path to the command `midi`
     *
     * @var string
     */
    protected $midiCurrentPath = null;

    /**
     * the http client
     *
     * @var Client
     */
    protected $client = null;

    protected function configure()
    {
        $this
            ->setName(self::CMD)
            ->setAliases(['upgrade'])
            ->setDescription('Update midi.phar to the latest version')
            ->addOption('--force', '-f', InputOption::VALUE_NONE,
                "Force replace local midi by the latest version in github", null)
            ->addOption('--unstable', null, InputOption::VALUE_NONE, 'Load unstable version from develop branch')
            ->addOption('--dry-run', null, InputOption::VALUE_NONE,
                "Test if there is any new version available without any version", null)
            ->setHelp('<info>The self-update will update <comment>midi</comment> if any newer version was found in github</info>');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init();

        $isDryRun = $input->getOption('dry-run');
        $unstable = $input->getOption('unstable');

        if ($unstable) {
            $versionTxtUlr = static::URL_VERSION_TXT_UNSTABLE;
            $midiUrl = static::URL_MIDI_UNSTABLE;
        } else {
            $versionTxtUlr = static::URL_VERSION_TXT_STABLE;
            $midiUrl = static::URL_MIDI_STABLE;
        }

        $latestVersion = (string)$this->getLatestVersion($output, $versionTxtUlr);

        $curVersion = Application::getMidiVersion();
        if (version_compare($curVersion, $latestVersion) >= 0) {
            $output->writeln("<info>BRAVO!!! You are using the latest midi version $curVersion</info>");
            return;
        }

        $output->writeln("<info>Try to update to version $latestVersion</info>");

        try {
            if (!$isDryRun) {
                $this->downloadNewMidi($output, $midiUrl);
                $this->clean($output);
            }

            $output->writeln('<info>Successfully updated</info>');
        } catch (Exception $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }
    }

    protected function init()
    {
        $this->res = Container::make('rootDir');
        $this->currentVersionTxt = $this->res . 'version.txt';

        $this->upgradeDir = Container::make('upgradeDir');
        $this->newMidiPath = Container::make('upgradeDir') . '/midi.phar';

        $this->midiCurrentPath = \Phar::running(false);

        $this->client = new Client();
    }

    /**
     * get latest version from github
     *
     * @param OutputInterface $output
     * @param string $versionTxtUrl
     *
     * @return \Psr\Http\Message\StreamInterface
     * @throws Exception
     */
    protected function getLatestVersion($output, $versionTxtUrl)
    {
        $output->writeln("<info>Try to get latest version from: $versionTxtUrl</info>",
            OutputInterface::VERBOSITY_VERBOSE);

        $resp = $this->client->get($versionTxtUrl);
        if ($resp->getStatusCode() != 200) {
            throw new \Exception("Get config from $versionTxtUrl failed: "
                . $resp->getStatusCode() . ' - ' . $resp->getReasonPhrase());
        }

        return $resp->getBody();
    }

    /**
     * @param OutputInterface $output
     * @param string $midiUrl
     *
     * @throws Exception
     */
    protected function downloadNewMidi($output, $midiUrl)
    {
        $resp = $this->client->get($midiUrl);
        if ($resp->getStatusCode() != 200) {
            throw new \Exception("Get midi.phar from $midiUrl failed: " . $resp->getStatusCode() . ' - ' . $resp->getReasonPhrase());
        }

        FileUtil::createFile($this->newMidiPath, true);
        file_put_contents($this->newMidiPath, $resp->getBody());

        @chmod($this->newMidiPath, 0777 & ~umask());
        // validate the phar
        $phar = new \Phar($this->newMidiPath);
        // free to unlock file
        unset($phar);

        FileUtil::moveFile($this->newMidiPath, $this->midiCurrentPath, true);
        @rename($this->newMidiPath, $this->midiCurrentPath);

        $output->writeln('<info>Done with midi.phar</info>');
    }

    protected function clean(OutputInterface $output)
    {
        $output->writeln("<info>Clean old tmp dir</info>", OutputInterface::VERBOSITY_VERBOSE);
        FileUtil::unlinkDir(Container::make('replayerDir'));
        FileUtil::unlinkDir(Container::make('reportDir') . DR . 'static');
        $output->writeln("<info>Clean old tmp dir done</info>", OutputInterface::VERBOSITY_VERBOSE);
    }
}
