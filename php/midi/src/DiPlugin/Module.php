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
    const MODULE = [
        'driver'           =>
            [
                'name'       => 'driver',
                'disf'       => 'disf!biz-gs-driver',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/driver/v2',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/api',
                'record-host' => 'biz-gsapi-web050.gz01',
                'uri'        => ['/gulfstream/driver/v2',],
            ],
        'passenger'        =>
            [
                'name'       => 'passenger',
                'disf'       => 'disf!biz-gs-biz_passenger',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/passenger/v2',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/api',
                'record-host' => 'biz-passenger-web025.gz01',
                'uri'        => ['/gulfstream/passenger/v2',],
            ],
        'internalApi'      =>
            [
                'name'       => 'internalApi',
                'disf'       => 'disf!biz-gs-internalapi',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/internalapi/v1',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/internalapi',
                'record-host' => 'biz-internal-api14.gz01',
                'uri'        => ['/gulfstream/internalapi/v1',],
            ],
        'themis'           =>
            [
                'name'       => 'themis',
                'disf'       => 'disf!biz-gs-themis',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/themis/v2',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/themis',
                'record-host' => 'biz-gsthemis-web07.gz01',
                'uri'        => ['/gulfstream/themis/v2',],
            ],
        'hermesapi'        =>
            [
                'name'       => 'hermesapi',
                'disf'       => 'disf!biz-gs-hermesapi',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/hermesapi/v1',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/hermesapi',
                'record-host' => 'biz-gshermes-api07.gz01',
                'uri'        => ['/gulfstream/hermesapi/v1',],
            ],
        'carpool'          =>
            [
                'name'       => 'carpool',
                'disf'       => 'disf!biz-gs-carpoolweb',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/carpool/v1',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/carpool',
                'record-host' => 'biz-carpool-web05.gz01',
                'uri'        => ['/gulfstream/carpool/v1',],
            ],
        'api'              =>
            [
                'name'   => 'api',
                'disf'   => 'disf!biz-gs-api',
                'deploy' => '/home/xiaoju/webroot/gulfstream/application/api/v1',
                'log'    => '/home/xiaoju/webroot/gulfstream/log/api',
                'uri'    => ['/gulfstream/api/v1',],
            ],
        'minos'            =>
            [
                'name'   => 'minos',
                'disf'   => 'disf!biz-gs-minos',
                'deploy' => '/home/xiaoju/webroot/gulfstream/application/minos/v1',
                'log'    => '/home/xiaoju/webroot/gulfstream/log/minos',
                'uri'    => ['/gulfstream/minos/v1',],
            ],
        'gs-openapi'       =>
            [
                'name'       => 'gs-openapi',
                'disf'       => 'disf!biz-gs-openapi',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/gs-openapi/v1',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/gs-openapi',
                'record-host' => 'biz-openapi-web14.gz01',
                'uri'        => ['/gulfstream/gs-openapi/v1',],
            ],
        'horae'            =>
            [
                'name'       => 'horae',
                'disf'       => 'disf!biz-gs-horae',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/horae/v1',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/horae',
                'record-host' => 'biz-gshorae-svr08.gz01',
                'uri'        => ['/gulfstream/horae/v1',],
            ],
        'openapi-inner'    =>
            [
                'name'       => 'openapi-inner',
                'disf'       => 'disf!biz-gs-openapi_inner',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/openapi-inner/v1',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/openapi-inner',
                'uri'        => ['/gulfstream/openapi-inner/v1',],
                'record-host' => 'biz-openapi-web14.gz01',
            ],
        'soter'            =>
            [
                'name'       => 'soter',
                'disf'       => 'disf!biz-gs-soter_api',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/soter/v2',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/soter',
                'record-host' => 'biz-soterapi-ygbh02.gz01',
                'uri'        => ['/gulfstream/soter/v2',],
            ],
        'bayze'            =>
            [
                'name'       => 'bayze',
                'disf'       => 'disf!biz-gs-bayze',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/bayze/v1',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/bayze',
                'record-host' => 'biz-gsthemis-web07.gz01',
                'uri'        => ['/gulfstream/bayze/v1',],
            ],
        'pandora'          =>
            [
                'name'       => 'pandora',
                'disf'       => 'disf!biz-gs-pandora',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/pandora/v1',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/pandora',
                'record-host' => 'biz-pandora-001.docker.gz01',
                'uri'        => ['/gulfstream/pandora/v1',],
            ],
        'passenger-center' =>
            [
                'name'       => 'passenger-center',
                'disf'       => 'disf!biz-gs-passenger_center',
                'deploy'     => '/home/xiaoju/webroot/gulfstream/application/passenger-center/v2',
                'log'        => '/home/xiaoju/webroot/gulfstream/log/passenger-center',
                'record-host' => 'biz-passenger-web025.gz01',
                'uri'        => ['/gulfstream/passenger/v2',],
            ],
        'post-sale'        => [
            'name'       => 'post-sale',
            'disf'       => 'disf!biz-gs-biz_passenger',
            'deploy'     => '/home/xiaoju/webroot/gulfstream/application/post-sale/v2',
            'log'        => '/home/xiaoju/webroot/gulfstream/log/post-sale',
            'record-host' => 'biz-passenger-web025.gz01',
            'uri'        => ['/gulfstream/passenger/v2',],
        ],
        'pre-sale'         => [
            'name'       => 'pre-sale',
            'disf'       => 'disf!biz-gs-biz_passenger',
            'deploy'     => '/home/xiaoju/webroot/gulfstream/application/pre-sale/v2',
            'log'        => '/home/xiaoju/webroot/gulfstream/log/pre-sale',
            'record-host' => 'biz-passenger-web025.gz01',
            'uri'        => ['/gulfstream/passenger/v2',],
        ],
        'performance'      => [
            'name'       => 'performance',
            'disf'       => 'disf!biz-gs-biz_passenger',
            'deploy'     => '/home/xiaoju/webroot/gulfstream/application/performance/v2',
            'log'        => '/home/xiaoju/webroot/gulfstream/log/performance',
            'record-host' => 'biz-passenger-web025.gz01',
            'uri'        => ['/gulfstream/passenger/v2',],
        ],
        'transaction'      => [
            'name'       => 'transaction',
            'disf'       => 'disf!biz-gs-biz_passenger',
            'deploy'     => '/home/xiaoju/webroot/gulfstream/application/transaction/v2',
            'log'        => '/home/xiaoju/webroot/gulfstream/log/transaction',
            'record-host' => 'biz-passenger-web025.gz01',
            'uri'        => ['/gulfstream/passenger/v2',],
        ],
        'titan'            => [
            'name'       => 'titan',
            'disf'       => 'disf!os-gs-titan_api',
            'deploy'     => '/home/xiaoju/webroot/gulfstream/application/mis/titan',
            'log'        => '/home/xiaoju/webroot/gulfstream/log/titan',
            'record-host' => 'os-ucmc-web00.gz01',
            'uri'        => ['/gulfstream/passenger/v2',],
        ],
    ];

    const DEPLOY_SYSTEM_PATH = '/home/xiaoju/webroot/gulfstream/application/system';
    const DEPLOY_BIZ_CONFIG_PATH = '/home/xiaoju/webroot/gulfstream/application/biz-config';
}