<?php

namespace DiPlugin\Uploader;

use Midi\Container;
use Midi\Exception\RuntimeException;
use Midi\Util\FileUtil;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class Uploader
{
    const CONNECT_TO = 0.3;
    const REQUEST_TO = 0.5;
    const APPID = 2;

    const LIMIT = 100;

    const ACTION_REPLAY = 1;
    const ACTION_SEARCH = 2;
    const ACTION_UPGRADE = 3;

    const ACTION_HANDLER = [
        self::ACTION_REPLAY => ReplayMate::class,
        self::ACTION_SEARCH => SearchMate::class,
    ];

    static $uploadUrl = '';

    public static function initUploadUrl()
    {
        static $init;
        if ($init) {
            return;
        }
        $midi = Container::make("midi");
        $config = $midi->getConfig();
        $uploadUrl = $config->get('php', 'uploader-url');
        if (empty($uploadUrl)) {
            throw new RuntimeException("Enable uploader, but can not find `uploader-url` in config.yml. Disable uploader by `enable-uploader: false` config");
        }
        self::$uploadUrl = $uploadUrl;
        $init = true;
    }

    public static function upload()
    {
        try {
            self::initUploadUrl();
            $client = new Client();
            /** @var PromiseInterface[] $promises */
            $promises = [];
            /** @var BaseMate $class */
            foreach (self::ACTION_HANDLER as $actionId => $class) {
                $promises[] = self::uploadAsync($client, $actionId, $class::getActions());
            }

            foreach ($promises as $promise) {
                if (null !== $promise) {
                    $promise->wait(false);
                }
            }
        } catch (\Exception $e) {
            // silence for all exception
            exit(0);
        }
    }

    /**
     * 异步上传记录
     *
     * @param $client
     * @param $actionId
     * @param $actionList
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function uploadAsync(Client $client, $actionId, $actionList)
    {
        $actionList = self::getActions($actionId, $actionList);
        if (0 == count($actionList)) {
            return null;
        }

        $promise = $client->postAsync(self::$uploadUrl, [
            'connect_timeout' => self::CONNECT_TO,
            'timeout'         => self::REQUEST_TO,
            'form_params'     => [
                'appid'  => self::APPID,
                'action' => $actionId,
                'time'   => time(),
                'data'   => json_encode($actionList, JSON_FORCE_OBJECT),
            ],
        ]);

        $promise->then(
            function (ResponseInterface $response) use ($actionId, $actionList) {
                if (200 != $response->getStatusCode()) {
                    Uploader::saveActions($actionId, $actionList);
                    return;
                }

                $result = json_decode($response->getBody(), true);
                if (JSON_ERROR_NONE !== json_last_error() || !isset($result['errno']) || 0 != $result['errno']) {
                    Uploader::saveActions($actionId, $actionList);
                    return;
                }

                Uploader::cleanActions($actionId);
            },
            function (RequestException $e) use ($actionId, $actionList) {
                Uploader::saveActions($actionId, $actionList);
            }
        );

        return $promise;
    }

    /**
     * 将本次产生的 action 列表和历史上传失败的列表合并返回, 列表元素最多可以为 $limit 个
     * 如果希望返回全部, 则可以设置 $limit = null
     *
     * @param     $actionId
     * @param     $actionList
     * @param int $limit
     *
     * @return array
     */
    protected static function getActions($actionId, $actionList, $limit = self::LIMIT)
    {
        $file = self::getFilePath($actionId);
        FileUtil::createFile($file);
        if (0 == filesize($file)) {
            return array_slice($actionList, 0, $limit);
        }
        foreach (explode("\n", file_get_contents($file)) as $line) {
            $action = json_decode($line, true);
            if (JSON_ERROR_NONE === json_last_error()) {
                $actionList[] = $action;
            }
        }

        return array_slice($actionList, 0, $limit);
    }

    /**
     * 将本次上传失败的 action 存储至本地, 最多存储 $limit 个
     * 如果希望存储全部, 则可以设置 $limit = null
     *
     * @param     $actionId
     * @param     $actionList
     * @param int $limit
     */
    protected static function saveActions($actionId, $actionList = [], $limit = self::LIMIT)
    {
        if (count($actionList) > $limit) {
            $actionList = array_slice($actionList, 0, $limit);
        }
        $result = [];
        foreach ($actionList as $action) {
            $line = json_encode($action);
            if (JSON_ERROR_NONE !== json_last_error()) {
                continue;
            }
            $result[] = $line;
        }
        file_put_contents(self::getFilePath($actionId), implode("\n", $result));
    }

    protected static function cleanActions($actionId)
    {
        FileUtil::createFile(self::getFilePath($actionId), true);
    }

    protected static function getFilePath($actionId)
    {
        return Container::make('logDir') . "/${actionId}.dt";
    }
}
