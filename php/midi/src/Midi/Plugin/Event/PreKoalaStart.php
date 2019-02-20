<?php
/**
 * @author tanmingliang
 */

namespace Midi\Plugin\Event;

use Midi\Midi;
use Midi\EventDispatcher\Event;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Application;

class PreKoalaStart extends Event
{

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var array
     */
    protected $options;

    /**
     * @var OutputInterface
     */
    protected $output;

    public function __construct(string $name, Midi $midi, Application $app, array $options, OutputInterface $output)
    {
        parent::__construct($name, $midi);
        $this->app = $app;
        $this->options = $options;
        $this->output = $output;
    }

    /**
     * @return Command
     */
    public function getCommand($name)
    {
        return $this->app->find($name);
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setOptions($options)
    {
        $this->options = $options;
    }

    public function getOutput()
    {
        return $this->output;
    }
}