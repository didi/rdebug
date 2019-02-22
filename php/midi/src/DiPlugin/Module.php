<?php
/**
 * Module's common info
 *
 * @author tanmingliang
 */

namespace DiPlugin;

/**
 * This class record module's information and used by DiPlugin to auto generate some context for Midi.
 * So user will not to config their project and its aim is for the convenience of users.
 */
class Module
{
    // lazy init by internal
    static $module = [
//        'xxx-module-name' => [
//            'name'        => 'xxx-module-name',
//            'disf'        => 'disf!xxx',
//            'deploy'      => '/path/to/your/deploy/dir',
//            'log'         => '/path/to/your/log/dir',
//            'record-host' => 'traffic-recorded-machine-name',
//            'uri'         => ['/uri',],
//        ],
    ];

    const DEPLOY_SYSTEM_PATH = '/home/xiaoju/webroot/gulfstream/application/system';
    const DEPLOY_BIZ_CONFIG_PATH = '/home/xiaoju/webroot/gulfstream/application/biz-config';
}