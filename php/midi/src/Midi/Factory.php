<?php declare(strict_types=1);
/**
 * @author tanmingliang
 */

namespace Midi;

use Midi\Console\Application;
use Midi\Exception\RuntimeException;
use Midi\EventDispatcher\EventDispatcher;
use Midi\EventDispatcher\EventSubscriberInterface;
use Midi\Plugin\PluginEvents;
use Midi\Plugin\PreloadPluginInterface;
use Midi\Resolver\FileResolver;
use Midi\Resolver\ResolverInterface;
use Midi\Koala\Koala;
use Midi\Differ\Differ;
use Midi\Differ\DifferInterface;
use Midi\Reporter\Reporter;
use Midi\Reporter\ReportInterface;
use Composer\Autoload\ClassLoader;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Stopwatch\Stopwatch;

final class Factory
{

    /**
     * Create Application.
     *
     * @param $autoloader
     * @param string|Config $config
     * @return Application
     * @throws Exception\ContainerException
     * @throws Exception\ContainerValueNotFoundException
     * @throws RuntimeException
     */
    public static function createApp(ClassLoader $autoloader, $config = null)
    {
        return new Application(self::createMidi($autoloader, $config));
    }

    /**
     * Create Midi.
     *
     * @param ClassLoader $autoloader
     * @param string|Config $config
     * @return Midi
     * @throws Exception\ContainerException
     * @throws Exception\ContainerValueNotFoundException
     * @throws RuntimeException
     */
    public static function createMidi(ClassLoader $autoloader, $config = null)
    {
        static $midi;
        if ($midi instanceof Midi) {
            return $midi;
        }

        if (!$config instanceof Config) {
            $config = self::createConfig(null, $config);
        }
        self::registerAutoloader($autoloader, $config);
        self::bindPaths($config->getRDebugDir());

        $midi = new Midi();
        $midi->setConfig($config);
        $dispatcher = new EventDispatcher($midi);
        $midi->setEventDispatcher($dispatcher);
        $midi->setKoala(new Koala($config, $dispatcher));
        $midi->setResolver(self::createResolver($config));
        $midi->setDiffer(self::createDiffer($config));
        $midi->setReporter(self::createReporter($midi, $config));

        self::initContainer($midi);
        self::registerPreloadPlugins($midi, $config);

        $dispatcher->dispatchEvent(PluginEvents::INIT);
        return $midi;
    }

    /**
     * Create Config.
     *
     * Merge from project & home & default config.
     *
     * Project Config > Home Config > Default Config
     *
     * @param string $cwd
     * @param string $config
     * @return Config
     * @throws RuntimeException
     */
    public static function createConfig(string $cwd = null, string $config = null)
    {
        $config = new Config($config);
        foreach (self::getConfigFiles($cwd) as $file) {
            $v = Yaml::parse(file_get_contents($file));
            $config->merge($v);
        }
        return $config;
    }

    /**
     * Two config files.
     *
     * Project config & Home global config. Priority: Project > Home.
     *
     * @param string $cwd
     * @return array
     * @throws RuntimeException
     */
    public static function getConfigFiles(string $cwd = null)
    {
        $dirs = [];
        $dir = self::getHomeConfigDir();
        if ($dir && file_exists($dir . DR . Config::MIDI_CONFIG_FILE)) {
            $dirs[] = $dir . DR . Config::MIDI_CONFIG_FILE;
        }

        $dir = self::getProjectConfigDir($cwd);
        if ($dir && file_exists($dir . DR . Config::MIDI_CONFIG_FILE)) {
            $dirs[] = $dir . DR . Config::MIDI_CONFIG_FILE;
        }
        return $dirs;
    }

    public static function getProjectConfigDir($cwd = null)
    {
        $cwd = $cwd ?: getcwd();
        $cwd = rtrim(strtr($cwd, '\\', '/'), '/');
        if (is_dir($cwd . DR . Config::MIDI_CONFIG_DIR)) {
            return $cwd . DR . Config::MIDI_CONFIG_DIR;
        }

        return false;
    }

    /**
     * @return string
     * @throws RuntimeException
     */
    public static function getHomeConfigDir()
    {
        $userDir = self::getUserDir();
        if (is_dir($userDir . DR . Config::MIDI_CONFIG_DIR)) {
            return $userDir . DR . Config::MIDI_CONFIG_DIR;
        }

        return false;
    }

    /**
     * @return string
     * @throws RuntimeException
     */
    public static function getUserDir()
    {
        $home = getenv('HOME');
        if (!$home) {
            throw new RuntimeException('The HOME environment variable must be set for midi to run correctly');
        }

        return rtrim(strtr($home, '\\', '/'), '/');
    }

    /**
     * Register autoloader
     * @param ClassLoader $autoloader
     * @param Config $config
     */
    public static function registerAutoloader(ClassLoader $autoloader, Config $config)
    {
        $loaders = $config->getAutoloader();
        if (isset($loaders['psr-0'])) {
            foreach ($loaders['psr-0'] as $prefix => $paths) {
                $paths = (array)$paths;
                $autoloader->add($prefix, $paths);
            }
        }
        if (isset($loaders['psr-4'])) {
            foreach ($loaders['psr-4'] as $prefix => $paths) {
                $paths = (array)$paths;
                $autoloader->addPsr4($prefix, $paths);
            }
        }
        if (isset($loaders['classmap'])) {
            $autoloader->addClassMap($loaders['classmap']);
        }
    }

    /**
     * @param Midi $midi
     * @param Config $config
     * @throws RuntimeException
     */
    protected static function registerPreloadPlugins(Midi $midi, Config $config)
    {
        $plugins = $config->get('php', 'preload-plugins');
        if (is_array($plugins) && count($plugins) > 0) {
            foreach ($plugins as $pluginClass) {
                if (!class_exists($pluginClass)) {
                    throw new RuntimeException("Plugin '$pluginClass' class not found!");
                }
                /* @var PreloadPluginInterface $plugin */
                $plugin = new $pluginClass;
                if (!$plugin instanceof PreloadPluginInterface) {
                    $type = PreloadPluginInterface::class;
                    throw new RuntimeException("Class '$pluginClass' not implement $type!");
                }
                $plugin->activate($midi);
                if ($plugin instanceof EventSubscriberInterface) {
                    $midi->getEventDispatcher()->addSubscriber($plugin);
                }
            }
        }
    }

    /**
     * @param $config Config
     * @return ResolverInterface
     * @throws RuntimeException
     */
    public static function createResolver(Config $config)
    {
        $resolverClass = $config->get('php', 'session-resolver');
        if (empty($resolverClass)) {
            return self::createDefaultResolver();
        }
        if (!class_exists($resolverClass)) {
            throw new RuntimeException("Resolver '$resolverClass' class not found!");
        }
        $resolver = new $resolverClass;
        if (!$resolver instanceof ResolverInterface) {
            $type = ResolverInterface::class;
            throw new RuntimeException("Class '$resolverClass' not implement $type!");
        }
        return $resolver;
    }

    public static function createDefaultResolver()
    {
        return new FileResolver();
    }

    /**
     * @param Config $config
     * @return DifferInterface
     * @throws RuntimeException
     */
    public static function createDiffer(Config $config)
    {
        $differClass = $config->get('php', 'differ');
        if (empty($differClass)) {
            return self::createDefaultDiffer();
        }
        if (!class_exists($differClass)) {
            throw new RuntimeException("Differ '$differClass' class not found!");
        }
        $differ = new $differClass;
        if (!$differ instanceof DifferInterface) {
            $type = DifferInterface::class;
            throw new RuntimeException("Class '$differClass' not implement $type!");
        }
        return $differ;
    }

    public static function createDefaultDiffer()
    {
        return new Differ();
    }

    /**
     * @param Midi $midi
     * @param Config $config
     * @return ReportInterface
     * @throws RuntimeException
     */
    public static function createReporter(Midi $midi, Config $config)
    {
        $reporterClass = $config->get('php', 'reporter');
        if (empty($reporterClass)) {
            return self::createDefaultReporter($midi);
        }
        if (!class_exists($reporterClass)) {
            throw new RuntimeException("Reporter '$reporterClass' class not found!");
        }
        $reporter = new $reporterClass($midi);
        if (!$reporter instanceof ReportInterface) {
            $type = ReportInterface::class;
            throw new RuntimeException("Class '$reporterClass' not implement $type!");
        }
        return $reporter;
    }

    public static function createDefaultReporter(Midi $midi)
    {
        return new Reporter($midi);
    }

    /**
     * bind to container.
     *
     * @param Midi $midi
     */
    private static function initContainer(Midi $midi)
    {
        Container::bind('midi', $midi);
        Container::bind('config', $midi->getConfig());
        Container::bind('stopWatch', function () {
            return new Stopwatch();
        });
        Container::bind('koalaLog', function () {
            $log = Container::make('logDir') . DR . 'koala.log';
            if (file_exists($log)) {
                unlink($log);
            }
            return $log;
        });
    }

    /**
     * Bind path to container
     *
     * @param string $rdebugDir
     * @throws RuntimeException
     */
    protected static function bindPaths($rdebugDir)
    {
        self::createDir($rdebugDir);
        Container::bind('rdebugDir', $rdebugDir);
        Container::bind('workingDir', getcwd());
        Container::bind('midiWorkingDir', function () {
            return self::createDir(Container::make('workingDir') . DR . Config::MIDI_WORKING_DIR);
        });
        Container::bind('rootDir', ROOT_PATH . '/');
        Container::bind('resDir', ROOT_PATH . '/res');
        Container::bind('templateDir', function () {
            return Container::make('resDir') . '/template/report';
        });
        Container::bind('pharReplayerDir', function () {
            return Container::make('resDir') . DR . 'replayer';
        });

        /**
         * rdebug
         *   - res
         *     - replayer
         *     - depends
         *   - session
         *   - log
         *   - upgrade
         *   - report
         *     - coverage
         *     - trace
         *         - tmp
         *     - static
         */
        Container::bind('toolResDir', function () use ($rdebugDir) {
            return self::createDir($rdebugDir . DR . 'res');
        });
        Container::bind('replayerDir', function () {
            return self::createDir(Container::make('toolResDir') . DR . 'replayer');
        });
        Container::bind('dependsDir', function () {
            return self::createDir(Container::make('toolResDir') . DR . 'depends');
        });
        Container::bind('sessionDir', function () use ($rdebugDir) {
            return self::createDir($rdebugDir . DR . 'session');
        });
        Container::bind('logDir', function () use ($rdebugDir) {
            return self::createDir($rdebugDir . DR . 'log');
        });
        Container::bind('upgradeDir', function () use ($rdebugDir) {
            return self::createDir($rdebugDir . DR . 'upgrade');
        });
        Container::bind('reportDir', function () use ($rdebugDir) {
            return self::createDir($rdebugDir . DR . 'report');
        });
        Container::bind('coverageDir', function () {
            return self::createDir(Container::make('reportDir') . DR . 'coverage');
        });
        Container::bind('traceDir', function () {
            return self::createDir(Container::make('reportDir') . DR . 'trace');
        });
        Container::bind('traceTmpDir', function () {
            return self::createDir(Container::make('traceDir') . DR . 'tmp');
        });
    }

    /**
     * Create dir.
     *
     * @param string $dir
     * @return string
     * @throws RuntimeException
     */
    public static function createDir($dir)
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            throw new RuntimeException("Create dir $dir failed!");
        }
        return $dir;
    }
}
