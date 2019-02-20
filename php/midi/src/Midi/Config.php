<?php declare(strict_types=1);

/**
 * @author tanmingliang
 */

namespace Midi;

use Midi\Exception\RuntimeException;
use Symfony\Component\Yaml\Yaml;

class Config
{

    const MIDI_CONFIG_DIR = '.midi';
    const MIDI_CONFIG_FILE = 'config.yml';
    const KOALA_PREPEND_FILE = 'prepend.php';

    /**
     * @var array
     */
    protected $config;

    public function __construct($file = null)
    {
        if ($file === null) {
            $file = __DIR__ . DR . 'Config.yml';
        }
        if (!file_exists($file)) {
            throw new RuntimeException("Invalid config file:$file");
        }
        $this->config = Yaml::parse(file_get_contents($file));
    }

    public function get(...$keys)
    {
        $nested = $this->config;
        foreach ($keys as $key) {
            if (!is_array($nested)) {
                return null;
            }
            if (!isset($nested[$key])) {
                return null;
            }
            $nested = $nested[$key];
        }
        return $nested;
    }

    /**
     * Merges new config values with the existing ones (overriding)
     *
     * @param array $config
     * @return array
     */
    public function merge($config)
    {
        if (!empty($config)) {
            $this->config = $this->recursionMerge($this->config, $config);
        }
        foreach (['preload-plugins', 'plugins', 'custom-commands'] as $subKey) {
            if (isset($this->config['php'][$subKey])) {
                $this->config['php'][$subKey] = array_unique($this->config['php'][$subKey]);
            }
        }
        return $this->config;
    }

    /**
     * Keep numeric key's value, overriding string key
     */
    protected function recursionMerge($old, $new)
    {
        $merged = $old ?? [];
        foreach ($new as $k => $v) {
            if (isset($merged[$k]) && is_array($merged[$k]) && is_array($v)) {
                $newValue = $this->recursionMerge($merged[$k], $v);
            } else {
                $newValue = $v;
            }
            if (is_string($k)) {
                $merged[$k] = $newValue;
            } else {
                $merged[] = $newValue;
            }
        }
        return $merged;
    }

    public function getAutoloader()
    {
        $autoloader = $this->get('php', 'autoloader');
        if (!is_array($autoloader) || count($autoloader) == 0) {
            return [];
        }
        return $autoloader;
    }

    public function getCustomCommands()
    {
        $customCommands = $this->get('php', 'custom-commands');
        if (!is_array($customCommands) || count($customCommands) == 0) {
            return [];
        }
        return $customCommands;
    }

    public function getRDebugDir()
    {
        return rtrim($this->get('rdebug-dir'), '/');
    }

    /**
     * Inject code allow you do something before execute scripts.
     *
     * Inject code will be called before koala replay session.
     * Each session, pre inject code will be same.
     *
     * @return string
     */
    public function getPreInjectCode()
    {
        static $code;
        if ($code) {
            return $code;
        }

        $inject = $this->get('php', 'pre-inject-file');
        if (empty($inject)) {
            return $code = '';
        }

        if (is_string($inject)) {
            $inject = [$inject,];
        }
        foreach ($inject as $file) {
            if (substr($file, 0, 5) === '<?php') {
                $code .= $file;
            } elseif (file_exists($file)) {
                $code .= file_get_contents($file);
            }
        }
        return $code;
    }

    /**
     * Prepend file implement by auto_prepend_file
     *
     * It will call PreInjectCode and some other codes(like Mock Storage)
     */
    public function getPrependFile()
    {
        return Container::make('mockDir') . DR . self::KOALA_PREPEND_FILE;
    }
}
