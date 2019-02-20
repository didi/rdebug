<?php declare(strict_types=1);

/**
 * @author tanmingliang
 */
namespace Midi\Command;

use Throwable;
use Exception;
use Midi\Koala\Replayed\ReplayedSession;
use Midi\Koala\Replaying\ReplayingSession;
use Midi\Midi;
use Midi\Resolver\FileResolver;
use Midi\Resolver\ResolverInterface;
use Midi\Koala\Koala;
use Midi\Differ\DifferInterface;
use Midi\Plugin\PluginEvents;
use Midi\Plugin\Event\CommandConfigureEvent;
use Midi\Koala\ParseRecorded;
use Midi\Util\Util;
use Midi\Container;
use Midi\Message;
use Midi\Util\BM;
use Midi\Reporter\Tracer;
use Midi\Reporter\Coverage;
use Midi\Exception\KoalaNotStartException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Stopwatch\Stopwatch;

class RunCommand extends BaseCommand
{
    const CMD = 'run';

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Midi
     */
    protected $midi;

    /**
     * @var ResolverInterface
     */
    protected $resolver;

    /**
     * @var Koala
     */
    protected $koala;

    /**
     * @var DifferInterface
     */
    protected $differ;

    /**
     * @var Stopwatch
     */
    protected $stopWatch;

    /**
     * @var bool
     *
     * Is load xdebug extension
     */
    protected $isLoadXdebug = false;

    /**
     * @var bool
     *
     * Is koala replay with Xdebug
     */
    protected $withXdebug = false;

    /**
     * @var bool
     *
     * Is generate Xdebug function trace
     */
    protected $isTrace = false;

    /**
     * @var bool
     *
     * Is generate Xdebug code coverage
     */
    protected $isCoverage = false;

    /**
     * @var bool
     *
     * Is generate replay report
     */
    protected $isReport = false;

    /**
     * @var array
     */
    protected $options = [];

    /**
     * @var array
     */
    protected $summary = [
        'sessionIds' => [],
        'diffSessionIds' => [],
    ];

    protected function configure() {
        $this
            ->setName(self::CMD)
            ->setAliases(['replay'])
            ->setDescription('Replay Files')
            ->addOption('--file', '-f', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Replay session by file')
            ->addOption('--xdebug', '-x', InputOption::VALUE_NONE, 'Replay with xdebug, if you want to debug your code, just set breakpoints')
            ->addOption('--report', '-R', InputOption::VALUE_NONE, 'Generate replay report')
            ->addOption('--trace', '-T', InputOption::VALUE_NONE, 'Generate Xdebug function traces')
            ->addOption('--coverage', '-C', InputOption::VALUE_NONE, 'Generate code coverage report')
            ->addOption('--open', '-O', InputOption::VALUE_NONE, 'After replayed, direct open report at browser')
            ->addOption('--match-strategy', '-M', InputOption::VALUE_OPTIONAL,
                'Set replay match strategy for traffic, support: `chunk` or `sim`', 'sim')
            ->addOption('--exclude-key', '-E', InputOption::VALUE_OPTIONAL, "Ignore some different value of keys, eg: -E 'abc,efg'", false)
            ->addOption('--display-diff-only', '-D', InputOption::VALUE_OPTIONAL,
                'Display different value only, default display all output to console', false)
            ->setHelp('<info>php midi.phar run -f session.json, use `php midi run -h` to see more...</info>');

        // Application is constructing.
        Container::make('midi')->getEventDispatcher()->dispatch(PluginEvents::COMMAND_CONFIGURE, new CommandConfigureEvent($this));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $ex = null;
        $replaySessionCount = 0;

        try {
            // start koala -> resolve session -> koala replay -> diff
            $this->init($input, $output);
            $this->startReplayer();
            $sessions = $this->querySessions($replaySessionCount);
            if (!$sessions->current()) {
                $output->writeln(Message::RUN_COMMAND_NO_SESSION_REPLAY);
                return 0;
            }
            if (!$this->koala->isStartUp()) {
                throw new Exception(Message::RUN_COMMAND_REPLAYER_NOT_START);
            }

            $progress = Util::getProgressBarObj($replaySessionCount);
            foreach ($sessions as $replayingSession) {
                try {
                    $replayedSession = $this->koala->replay($replayingSession);
                    $this->doAnalysis($replayingSession, $replayedSession);
                } catch (Throwable $t) {
                    $output->writeln($t->getMessage());
                    if ($t instanceof KoalaNotStartException) {
                        break;
                    }
                }
                $progress->advance();
                $output->writeln(PHP_EOL);
            }

            $this->doSummary($replaySessionCount);
            $this->doReport();
        } catch (Exception $e) {
            $ex = $e;
            $output->writeln($e->getMessage());
        }

        $this->getEventDispatcher()->dispatchCommandEvent(PluginEvents::POST_COMMAND_RUN,
            $this->getName(), $input, $output,
            ['replaySessionCount' => $replaySessionCount, 'summary' => $this->summary, 'exception' => $ex,]
        );
        return $ex === null ? 0 : ($ex->getCode() !== 0 ? $ex->getCode() : 1);
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws Exception
     */
    private function init(InputInterface $input, OutputInterface $output)
    {
        $this->stopWatch = Container::make('stopWatch');
        $this->stopWatch->start('summary');

        $this->input = $input;
        $this->output = $output;
        $this->midi = $this->getMidi();
        $this->resolver = $this->midi->getResolver();
        $this->koala = $this->midi->getKoala();
        $this->koala->setOutput($output);
        $this->differ = $this->midi->getDiffer();
        $this->summary = [];

        if (extension_loaded('xdebug')) {
            $this->isLoadXdebug = true;
        }
        if ($input->getOption('xdebug')) {
            if ($this->isLoadXdebug) {
                $this->withXdebug = true;
            } else {
                $this->output->writeln(Message::RUN_COMMAND_NOT_FOUND_XDEBUG);
            }
        }
        if ($input->getOption('coverage')) {
            if ($this->isLoadXdebug) {
                $this->isCoverage = true;
            } else {
                $this->output->writeln(Message::RUN_COMMAND_NOT_FOUND_COVERAGE);
            }
        }
        if ($input->getOption('trace')) {
            if ($this->isLoadXdebug) {
                $this->isTrace = true;
            } else {
                $this->output->writeln(Message::RUN_COMMAND_NOT_FOUND_TRACE);
            }
        }
        if ($input->getOption('report')) {
            $this->isReport = true;
        }

        $this->options = [
            'isLoadXdebug' => $this->isLoadXdebug,
            'withXdebug'   => $this->withXdebug,
            'isCoverage'   => $this->isCoverage,
            'isTrace'      => $this->isTrace,
            'isReport'     => $this->isReport,
        ];
        $this->differ->setOptions($this->options, $input, $output);
        Coverage::setOptions($this->options, $input, $output);
    }

    private function startReplayer()
    {
        BM::start(BM::START_REPLAYER);
        $input = [
            '--start'          => 1,
            '--match-strategy' => $this->input->getOption('match-strategy'),
        ];
        if ($this->isTrace) {
            $input['--trace'] = $this->input->getOption('trace');
        }
        if ($this->withXdebug) {
            $input['--xdebug'] = $this->input->getOption('xdebug');
        }

        $command = $this->getApplication()->find('replayer');
        $command->run(new ArrayInput($input), $this->output);
        BM::stop(BM::START_REPLAYER);
    }

    /**
     * @param int $getCount
     * @return \Generator
     */
    private function querySessions(&$getCount)
    {
        $this->getEventDispatcher()->dispatchSolving(PluginEvents::PRE_SESSIONS_SOLVING, $this->input, $this->output);

        /** @see FileResolver::query() default resolver */
        BM::start(BM::RESOLVE_SESSIONS);
        $sessions = $this->resolver->resolve($this->input, $this->output, $this->options);
        BM::stop(BM::RESOLVE_SESSIONS);

        $this->getEventDispatcher()->dispatchSolving(
            PluginEvents::POST_SESSIONS_SOLVING,
            $this->input,
            $this->output,
            $sessions
        );

        $getCount = count($sessions);
        if ($getCount > 1) {
            $this->output->writeln("<info>Finally will replay $getCount sessions.</info>");
        }

        foreach ($sessions as $session) {
            yield ParseRecorded::toReplayingSession($this->midi, $session, $this->withXdebug);
        }
    }

    /**
     * @param ReplayingSession $replayingSession
     * @param ReplayedSession $replayedSession
     * @return bool exist different
     */
    protected function doAnalysis(ReplayingSession $replayingSession, ReplayedSession $replayedSession)
    {
        $sessionId = $replayingSession->getSessionId();
        $isSame = $this->differ->diff($replayingSession, $replayedSession);

        $this->summary['sessionIds'][$sessionId] = $isSame;
        if (!$isSame) {
            $this->summary['diffSessionIds'][] = $sessionId;
        }

        $traceFile = '';
        if ($this->isTrace) {
            $traceFile = Tracer::renderTraces2Html($sessionId);
        }
        if ($this->isReport) {
            /* collect data first, do report later */
            $context = ['same' => $isSame, 'trace' => $traceFile,];
            $this->midi->getReporter()->collect($replayingSession, $replayedSession, $context);
        }

        return $isSame;
    }

    protected function doSummary($sessionCount)
    {
        $diffSessionIds = $this->summary['diffSessionIds'];
        $spent = $this->stopWatch->stop('summary')->getDuration() / 1000;
        $suffix = $this->withXdebug ? ' with Xdebug.' : '.';

        if ($sessionCount) {
            if (is_array($diffSessionIds) && count($diffSessionIds)) {
                $summary = sprintf(Message::SUMMARY_DO_REPLAY_EXIST_DIFF, $sessionCount, count($diffSessionIds), $spent,
                    $suffix, implode(' ', $diffSessionIds), implode(' -s ', $diffSessionIds));
            } else {
                $summary = sprintf(Message::SUMMARY_DO_REPLAY_NO_DIFF, $sessionCount, $spent, $suffix);
            }
        } else {
            $summary = sprintf(Message::SUMMARY_NO_REPLAY, $spent, $suffix);
        }

        $this->output->writeln($summary);
    }

    protected function doReport()
    {
        $open = $this->input->getOption('open');
        if ($this->isCoverage) {
            $coverage = Coverage::renderHTML($open);
        }
        if ($this->isReport) {
            if (!empty($coverage)) {
                $this->midi->getReporter()->render(['open' => $open, 'coverage' => $coverage,]);
            } else {
                $this->midi->getReporter()->render(['open' => $open,]);
            }
        }
    }
}
