<?php declare(strict_types=1);

/**
 * @author tanmingliang
 */

namespace DiPlugin;

use DiPlugin\Command\SearchCommand;
use DiPlugin\Util\Helper;
use DiPlugin\Util\PreKoalaCheck;
use DiPlugin\Mock\MockDir;
use DiPlugin\Mock\MockFile;
use DiPlugin\Uploader\BaseMate;
use DiPlugin\Uploader\SearchMate;
use DiPlugin\Mock\FixCI;
use DiPlugin\Uploader\ReplayMate;
use Midi\Midi;
use Midi\Container;
use Midi\Command\RunCommand;
use Midi\Plugin\Event\PreKoalaStart;
use Midi\Plugin\Event\PreCommandEvent;
use Midi\Plugin\Event\PostCommandEvent;
use Midi\Plugin\Event\PostParseSessionEvent;
use Midi\Plugin\PluginInterface;
use Midi\Plugin\PluginEvents;
use Midi\Reporter\Coverage;
use Midi\Util\Util;
use Midi\EventDispatcher\EventSubscriberInterface;
use Midi\Plugin\Event\PostReplaySessionEvent;
use Midi\Plugin\Event\PreReplaySessionEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Exception\CommandNotFoundException;

class Plugin implements PluginInterface, EventSubscriberInterface
{

    /**
     * @var $output OutputInterface
     */
    protected static $output;

    public function activate(Midi $midi, InputInterface $input, OutputInterface $output)
    {
        if ($output === null) {
            $output = new ConsoleOutput();
        }
        self::$output = $output;

        /**
         * For ci system 和 biz-config
         *
         * 目录是和 业务模块平级
         *
         * CI 框架的项目 如果没有 system ，将 git clone 到 res/depends/ciSystem
         */
        Container::bind('bizConfigDir', function () {
            return dirname(Container::make('workingDir')) . '/biz-config';
        });
        Container::bind('ciSystemDir', function () {
            $dir = dirname(Container::make('workingDir')) . '/system';
            if (!is_dir($dir)) {
                return Container::make('dependsDir') . DR . 'ciSystem';
            }
            return $dir;
        });
        Container::bind('DiPluginResDir', function () {
            return Container::make('resDir') . DR . 'diplugin';
        });
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            PluginEvents::PRE_KOALA_START     => 'onPreKoalaStart',
            PluginEvents::PRE_COMMAND_RUN     => 'onPreCommandRun',
            PluginEvents::POST_COMMAND_RUN    => 'onPostCommandRun',
            PluginEvents::POST_PARSE_SESSION  => 'onPostParseSession',
            PluginEvents::PRE_REPLAY_SESSION  => 'onPreReplaySession',
            PluginEvents::POST_REPLAY_SESSION => 'onPostReplaySession',
        );
    }

    /**
     * @param PreKoalaStart $event
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     * @throws \Midi\Exception\Exception
     */
    public static function onPreKoalaStart(PreKoalaStart $event)
    {
        PreKoalaCheck::prepareCISystem();
        PreKoalaCheck::checkProjectDir();
        PreKoalaCheck::checkPhpExt();

        /**
         * didi internal: generate & mock disf file
         *
         * @see \DiPlugin\Command\InitCommand::execute
         */
        try {
            $command = $event->getCommand('init');
        } catch (CommandNotFoundException $e) {
            self::$output->writeln("<comment>Can find init command: `DiPlugin\Command\InitCommand`, mock disf will not work!</comment>");
            return ;
        }
        $command->run(new ArrayInput(['--increase' => 1,]), $event->getOutput());
    }

    /**
     * Before command run
     *
     * 1. run & search command: uploader will upload replayed data by @param PreCommandEvent $event
     * @see BaseMate::collect
     *
     */
    public static function onPreCommandRun(PreCommandEvent $event)
    {
        $cmd = $event->getCommandName();
        $input = $event->getInput();

        if ($cmd === RunCommand::CMD) {
            BaseMate::collect(RunCommand::class);

            self::doPreRunCommand($event);
        } elseif ($cmd == SearchCommand::CMD) {
            BaseMate::collect(SearchCommand::class);
            SearchMate::$count = $input->getOption('count');
        }
    }

    /**
     * After command run
     *
     * 1. run command: uploader if catch exception
     *
     * @param PostCommandEvent $event
     */
    public static function onPostCommandRun(PostCommandEvent $event)
    {
        $cmd = $event->getCommandName();
        $args = $event->getArguments();

        if ($cmd === RunCommand::CMD) {
            if (isset($args['exception']) && $args['exception'] instanceof \Exception) {
                /** @var \Exception $ex */
                $ex = $args['exception'];
                BaseMate::$mark['status'] = 0;
                BaseMate::$mark['msg'] = $ex->getMessage();
            }
            if (isset($args['summary']) && isset($args['summary']['diffSessionIds'])) {
                foreach ($args['summary']['diffSessionIds'] as $sessionId) {
                    ReplayMate::$sessions[$sessionId]['same'] = 0;
                }
            }
        }
    }

    /**
     * Before run, update redirect-dir config
     *
     * add some didi's dir redirect logic, eg: online dirs to offline dirs
     * different session use the same redirect, so update config pre run command
     */
    protected static function doPreRunCommand(PreCommandEvent $event)
    {
        $config = $event->getMidi()->getConfig();

        // Copy \DiPlugin\DiConfig to \Midi\Config, must be before MockDir::getRedirectDir
        DiConfig::copy2MidiConfig($config);

        // Add mock dirs and inject codes
        $redirectDir = MockDir::getRedirectDir();
        if (!empty($redirectDir)) {
            $config->merge([
                'redirect-dir' => $redirectDir,
                'php'          => [
                    'pre-inject-file' => [__DIR__ . DR . 'Inject.php'],
                ],
            ]);
        }

        $input = $event->getInput();
        if (DiConfig::isCIFramework()) {
            FixCI::fix();
        }

        // php code coverage
        if ($input->getOption('coverage') && $module = DiConfig::getModuleName()) {
            // DiPlugin maybe have module dist, if exist and use it
            $isCI = DiConfig::isCIFramework();
            $dir = Container::make('DiPluginResDir') . DR . 'coverage';
            $dist = $dir . DR . $module . '.xml.dist';
            if (file_exists($dist)) {
                Coverage::setPhpUnitDist($dist);
                if (!$isCI) {
                    // Nuwa framework copy original dist, and replace to local path
                    // Because nuwa coverage data are local path, but dist are deploy path
                    // If not replace, data will be filter by deploy path and get empty coverage data
                    $midiDist = Container::make('workingDir') . DR . '.midi' . DR . Coverage::DIST;
                    if (!file_exists($midiDist)) {
                        // if not exist just copy or keep
                        $content = file_get_contents($dist);
                        $redirectDir = $config->get('redirect-dir');
                        if (is_array($redirectDir) && count($redirectDir)) {
                            foreach ($redirectDir as $from => $to) {
                                $content = str_replace($from, $to, $content);
                            }
                        }
                        file_put_contents($midiDist, $content);
                        Coverage::setPhpUnitDist($midiDist);
                    }
                }
            }
            if ($isCI) {
                // CI framework add autoloader rules
                Coverage::setTemplate(Coverage::getTemplate() . Reporter\Reporter::$coverageCIAppendCode);
            }
        }
    }

    public static function onPostParseSession(PostParseSessionEvent $event)
    {
        $actions = $event->getActions();

        $mockFiles = MockFile::buildMockFiles($actions);
        $event->setMockFiles(array_merge($event->getMockFiles(), $mockFiles));

        // display request info
        $args = $event->getArguments();
        $parsedInbound = $args['parsedInbound'];
        if (isset($args['isHTTP']) && $args['isHTTP'] && count($parsedInbound)) {
            $title = "<info>Request URI:</info> " . $parsedInbound['URI'];
            $splitLine = Util::buildSplitLine($title);
            self::$output->writeln([$splitLine, $title]);
            if (self::$output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $occurAt = $event->getCallFromInbound()->getOccurredAt();
                $requestTime = date('Y-m-d H:i:s', intval($occurAt / 1000000000));
                self::$output->writeln("<info>Request Time: <comment>$requestTime</comment></info>");
                self::$output->writeln("<info>Request:\n<comment>" . $parsedInbound['Req'] . "</comment></info>");
            }
        }
    }

    public static function onPreReplaySession(PreReplaySessionEvent $event)
    {
        $sessionId = $event->getReplayingSession()->getSessionId();
        self::$output->writeln("<info>Replaying SessionId: <comment>$sessionId</comment></info>");
    }

    public static function onPostReplaySession(PostReplaySessionEvent $event)
    {
        $replayed = $event->getReplayedSession();
        $sessionId = $replayed->getSessionId();
        $args = $event->getArgs();
        ReplayMate::$sessions[$sessionId] = [
            'id'      => Helper::guid(RunCommand::CMD),
            'latency' => $args['duration'],
            'same'    => 1,
        ];
    }
}