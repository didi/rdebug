<?php declare(strict_types=1);

/**
 * Generate HTML report
 *
 * @author tanmingliang
 */

namespace Midi\Reporter;

use Midi\Midi;
use Midi\Container;
use Midi\Koala\Replaying\ReplayingSession;
use Midi\Koala\Replayed\ReplayedSession;
use Midi\Parser\ParseReplayedInterface;
use Midi\Parser\ParseReplayed;
use Twig_Loader_Filesystem;

class Reporter implements ReportInterface
{
    /**
     * @var Midi
     */
    protected $midi;

    /**
     * @var Twig_Loader_Filesystem
     */
    protected $twigLoader;

    /**
     * @var ParseReplayedInterface
     */
    protected $parser;

    /**
     * report nav tabs
     *
     * @var array
     */
    protected $navTabLayouts = [
        [
            'name'     => 'Request & Response',
            'href'     => 'session-nav-request',
            'template' => 'replayed-tab-request.twig',
        ],
        [
            'name'     => 'Upstream Calls',
            'href'     => 'session-nav-upstream',
            'template' => 'replayed-tab-upstream.twig',
        ],
        [
            'name'     => 'Traces',
            'href'     => 'session-nav-trace',
            'template' => 'replayed-tab-trace.twig',
        ],
        [
            'name'     => 'Coverage',
            'href'     => 'session-nav-coverage',
            'template' => 'replayed-tab-coverage.twig',
        ],
        [
            'name'     => 'Logs',
            'href'     => 'session-nav-log',
            'template' => 'replayed-tab-log.twig',
        ],
    ];

    /**
     * render data
     * @var array
     */
    protected $renderData = [];

    public function __construct(
        Midi $midi,
        Twig_Loader_Filesystem $twigLoader = null,
        ParseReplayedInterface $parser = null
    ) {
        $this->midi = $midi;
        if ($twigLoader === null) {
            $templateDir = Container::make('templateDir') . DR;
            $twigLoader = new Twig_Loader_Filesystem($templateDir);
        }
        $this->twigLoader = $twigLoader;
        if ($parser === null) {
            $parser = new ParseReplayed($midi);
        }
        $this->parser = $parser;
    }

    public function addLoaderPath(string $path)
    {
        $this->twigLoader->addPath($path);
    }

    /**
     * @param array $navTabLayouts
     */
    public function setNavTabLayouts(array $navTabLayouts)
    {
        $this->navTabLayouts = $navTabLayouts;
    }

    /**
     * @return array
     */
    public function getNavTabLayouts()
    {
        return $this->navTabLayouts;
    }

    public function collect(ReplayingSession $replayingSession, ReplayedSession $replayedSession, array $context = [])
    {
        $this->renderData[] = [
            'replaying' => $replayingSession,
            'replayed'  => $replayedSession,
            'context'   => $context,
        ];
    }

    public function render($options)
    {
        $openReport = $options['open'];
        $output = Container::make("output");
        $matchThreshold = $this->midi->getKoala()->getMatchThreshold();
        $twig = new \Twig_Environment($this->twigLoader);
        $template = $twig->load('index.twig');

        $allSucc = true;
        $succCount = 0;
        $errorCount = 0;
        $sessionIds = [];
        $sessionsData = [];

        $output->writeln("<info>Generating Replayed Report...</info>");
        foreach ($this->renderData as $session) {
            $sessionCtx = $session['context'];
            if (isset($sessionCtx['same']) && $sessionCtx['same'] === true) {
                $same = 1;
            } else {
                $same = 0;
            }

            /* @var ReplayingSession $replaying */
            $replaying = $session['replaying'];
            /* @var ReplayedSession $replayed */
            $replayed = $session['replayed'];
            if (!$same) {
                ++$errorCount;
            } else {
                ++$succCount;
            }

            $data = $this->parser->doParse($replaying, $replayed, ParseReplayed::ACTION_ALL);
            $data = $this->format($data);

            $data['Same'] = $same;
            if (isset($sessionCtx['trace']) && !empty($sessionCtx['trace'])) {
                $data['TraceUrl'] = $sessionCtx['trace'];
            }
            if ($options['coverage']) {
                $data['CoverageUrl'] = $options['coverage'];
            }
            $sessionsData[] = $data;
            $sessionIds[] = $replayed->getSessionId();
        }
        if ($errorCount > 0) {
            $allSucc = false;
        }

        $context = [
            'replayedAlter'       => [
                'all_success' => $allSucc ? 1 : 0,
                'success'     => $succCount,
                'error'       => $errorCount,
            ],
            'replayedSessions'    => [
                'navTabs'  => $this->navTabLayouts,
                'sessions' => $sessionsData,
            ],
            'similarityThreshold' => 100 * $matchThreshold,
        ];
        $html = $template->render($context);

        // midi.html file keep newest report
        $reportDir = Container::make("reportDir");
        $file = sprintf("%s%s%s.html", $reportDir, DR, 'midi');
        file_put_contents($file, $html);

        // sessionId or md5(sessionIds) keep copy report
        if (count($sessionIds) > 1) {
            $name = md5(implode(',', $sessionIds));
        } else {
            $name = $sessionIds[0];
        }
        $file = sprintf("%s%s%s.html", $reportDir, DR, $name);
        file_put_contents($file, $html);

        if ($openReport) {
            system("open $file");
        }

        $output->writeln("<info>Replayed HTML Report: $file</info>");
        return $file;
    }

    /**
     * @return ParseReplayedInterface
     */
    public function getParser(): ParseReplayedInterface
    {
        return $this->parser;
    }

    public function format($data)
    {
        // AppendFiles report as logs
        $data['Logs'] = self::formatAppendFile($data['AppendFiles']);

        return $data;
    }

    /**
     * Summary by filename
     *
     * @param array $files
     * @return array
     */
    public static function formatAppendFile($files)
    {
        if (empty($files)) {
            return [];
        }
        $summary = [];
        $coverageDir = Container::make('coverageDir');
        foreach ($files as $fileInfo) {
            if (strpos($fileInfo['File'], $coverageDir) !== false) {
                continue;
            }
            $file = basename($fileInfo['File']);
            if (!isset($summary[$file])) {
                $summary[$file] = [];
            }
            $summary[$file][] = $fileInfo['Content'];
        }

        return $summary;
    }
}
