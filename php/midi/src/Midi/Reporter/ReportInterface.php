<?php declare(strict_types=1);
/**
 * @author tanmingliang
 */

namespace Midi\Reporter;

use Midi\Parser\ParseReplayedInterface;
use Midi\Koala\Replayed\ReplayedSession;
use Midi\Koala\Replaying\ReplayingSession;

interface ReportInterface
{
    /**
     * Add twig loader path.
     *
     * You could add your nav tabs and need add template path.
     *
     * @param string $path
     */
    public function addLoaderPath(string $path);

    /**
     * Set report nav tabs.
     *
     * @param array $tabs
     */
    public function setNavTabLayouts(array $tabs);

    /**
     * Get current report nav tabs.
     */
    public function getNavTabLayouts();

    /**
     * Get parser of reporter
     *
     * @return ParseReplayedInterface
     */
    public function getParser(): ParseReplayedInterface;

    /**
     * Collect template data.
     *
     * @param ReplayingSession $replayingSession
     * @param ReplayedSession $replayedSession
     * @param array $context
     */
    public function collect(ReplayingSession $replayingSession, ReplayedSession $replayedSession, array $context = []);

    /**
     * Render report template.
     *
     * @param array $options
     */
    public function render($options);
}