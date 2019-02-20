<?php

/**
 * @author fangjunda
 */

namespace DiPlugin\Uploader;

use Midi\Midi;
use Midi\Container;
use Midi\Command\RunCommand;
use Midi\Console\Application;
use DiPlugin\DiConfig;
use DiPlugin\Util\Helper;
use DiPlugin\Command\SearchCommand;

// @TODO Optimize
abstract class BaseMate
{
    const MATCH = [
        RunCommand::class    => ReplayMate::class,
        SearchCommand::class => SearchMate::class,
    ];

    public static $mark = [
        'pid'       => '',
        'user'      => '',
        'project'   => '',
        'version'   => '',
        'command'   => '',
        'options'   => '',
        'status'    => 1,
        'msg'       => '',
        'action_at' => 0,
    ];

    protected static $set = false;

    final public static function isSet()
    {
        return static::$set;
    }

    final public static function setUp()
    {
        static::$set = true;
    }

    final public static function enable()
    {
        static $enable;
        if ($enable === null) {
            /** @var Midi $midi */
            $midi = Container::make("midi");
            $isEnable = $midi->getConfig()->get('php', 'enable-uploader');
            $enable = !empty($isEnable) ? true : false;
        }
        return $enable;
    }

    final public static function collect($class)
    {
        if (!self::enable()) {
            return;
        }

        $mateClass = self::MATCH[$class];
        $mateClass::setUp();

        if (self::isSet()) {
            return;
        }

        $input = Container::make('input');

        self::$mark['pid'] = self::$mark['pid'] ?: Helper::guid($class::CMD);
        self::$mark['user'] = self::$mark['user'] ?: Helper::user();
        self::$mark['project'] = self::$mark['project'] ?: DiConfig::getModuleName();
        self::$mark['version'] = self::$mark['version'] ?: Application::getMidiVersion();
        self::$mark['command'] = self::$mark['command'] ?: $class::CMD;
        self::$mark['options'] = self::$mark['options'] ?: Helper::optionFormat($input->getOptions());
        self::$mark['action_at'] = self::$mark['action_at'] ?: strval(time());

        self::setUp();

        register_shutdown_function([Uploader::class, 'upload']);
    }

    abstract public static function getActions();
}
