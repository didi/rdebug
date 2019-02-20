<?php declare(strict_types=1);

/**
 * @author tanmingliang
 */

namespace Midi\Console;

use Midi\Container;
use Midi\Midi;
use Midi\Command;
use Midi\Config;
use Midi\Exception\RuntimeException;
use Midi\Plugin\PluginInterface;
use Midi\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication
{
    const NAME = 'Rdebug Midi';

    private static $version = null;

    /**
     * @var string
     */
    private static $logo = '
    __  ___   _        __   _ 
   /  |/  /  (_)  ____/ /  (_)
  / /|_/ /  / /  / __  /  / / 
 / /  / /  / /  / /_/ /  / /  
/_/  /_/  /_/   \__,_/  /_/   

';

    /**
     * @var Midi
     */
    protected $midi;

    protected $isInitialized = false;

    /**
     * Create console application.
     *
     * @param Midi $midi
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    public function __construct(Midi $midi)
    {
        $this->midi = $midi;
        parent::__construct(self::NAME, self::getMidiVersion());
    }

    /**
     * {@inheritDoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->init($input, $output);
        return parent::doRun($input, $output);
    }

    protected function getDefaultCommands()
    {
        $commands = array_merge(parent::getDefaultCommands(),
            array(
                new Command\ReplayerCommand(),
                new Command\RunCommand(),
            )
        );

        return $commands;
    }

    /**
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @param array $config
     * @throws RuntimeException
     */
    public function init(InputInterface $input = null, OutputInterface $output = null)
    {
        if ($this->isInitialized) {
            return;
        }

        $config = $this->midi->getConfig();
        $this->registerCustomCommands($config);
        $this->registerPlugins($config, $input, $output);

        Container::bind('app', $this);
        Container::bind('input', $input);
        Container::bind('output', $output);

        $this->isInitialized = true;
    }

    /**
     * Before register custom command, register autoloader first
     *
     * @param Config $config
     * @throws RuntimeException
     */
    protected function registerCustomCommands(Config $config)
    {
        $commands = $config->getCustomCommands();
        if (empty($commands)) {
            return;
        }
        foreach ($commands as $commandClass) {
            if (!class_exists($commandClass)) {
                throw new RuntimeException(
                    "Can not find custom command class '$commandClass' as command to the application."
                );
            }
            $customCommand = new $commandClass;
            $this->add($customCommand);
        }
    }

    /**
     * @param Config $config
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws RuntimeException
     */
    protected function registerPlugins(Config $config, InputInterface $input, OutputInterface $output)
    {
        $plugins = $config->get('php', 'plugins');
        if (empty($plugins)) {
            return;
        }
        foreach ($plugins as $pluginClass) {
            if (!class_exists($pluginClass)) {
                throw new RuntimeException("Plugin '$pluginClass' class not found!");
            }
            /* @var PluginInterface $plugin */
            $plugin = new $pluginClass;
            if (!$plugin instanceof PluginInterface) {
                $type = PluginInterface::class;
                throw new RuntimeException("Class '$pluginClass' not implement $type!");
            }
            $plugin->activate($this->midi, $input, $output);
            if ($plugin instanceof EventSubscriberInterface) {
                $this->midi->getEventDispatcher()->addSubscriber($plugin);
            }
        }
    }

    /**
     * @return Midi
     */
    public function getMidi()
    {
        return $this->midi;
    }

    /**
     * @return string
     */
    public function getHelp()
    {
        return self::$logo . parent::getHelp();
    }

    /**
     * @return null|string
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    public static function getMidiVersion()
    {
        if (null === self::$version) {
            self::$version = trim(file_get_contents(Container::make('rootDir') . 'version.txt'));
        }
        return self::$version;
    }
}
