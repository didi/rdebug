<?php declare(strict_types=1);

/**
 * @author tanmingliang
 */

namespace Midi;

use Midi\EventDispatcher\EventDispatcher;
use Midi\Reporter\ReportInterface;
use Midi\Resolver\ResolverInterface;
use Midi\Koala\Koala;
use Midi\Differ\DifferInterface;

class Midi
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var Koala
     */
    private $koala;

    /**
     * @var ResolverInterface
     */
    private $resolver;

    /**
     * @var DifferInterface
     */
    private $differ;

    /**
     * @var ReportInterface
     */
    private $reporter;

    /**
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * @param Config $config
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return EventDispatcher
     */
    public function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher;
    }

    /**
     * @param EventDispatcher $eventDispatcher
     */
    public function setEventDispatcher(EventDispatcher $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @return Koala
     */
    public function getKoala(): Koala
    {
        return $this->koala;
    }

    /**
     * @param Koala $koala
     */
    public function setKoala(Koala $koala)
    {
        $this->koala = $koala;
    }

    /**
     * @return ResolverInterface
     */
    public function getResolver(): ResolverInterface
    {
        return $this->resolver;
    }

    /**
     * @param ResolverInterface $resolver
     */
    public function setResolver(ResolverInterface $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * @return DifferInterface
     */
    public function getDiffer(): DifferInterface
    {
        return $this->differ;
    }

    /**
     * @param DifferInterface $differ
     */
    public function setDiffer(DifferInterface $differ)
    {
        $this->differ = $differ;
    }

    /**
     * @return ReportInterface
     */
    public function getReporter(): ReportInterface
    {
        return $this->reporter;
    }

    /**
     * @param ReportInterface $reporter
     */
    public function setReporter(ReportInterface $reporter)
    {
        $this->reporter = $reporter;
    }
}
