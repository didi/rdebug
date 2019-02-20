<?php
/**
 * @author tanmingliang
 */

namespace Midi\Resolver;

use Midi\Message;
use Midi\Exception\RuntimeException;
use Midi\Container;
use Midi\Command\RunCommand;
use Midi\Exception\Exception;
use Midi\Exception\ResolveInvalidParam;
use Midi\Plugin\Event\CommandConfigureEvent;
use Midi\Util\Util;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;

/**
 * Resolver session from elastic.
 *
 * You could search session by uri, request body keywords, response and upstream's request, upstream's response.
 *
 * Before use elastic, you should config `elastic-search-url` in your config.yml.
 */
class ElasticResolver extends FileResolver
{
    /**
     * @var array
     */
    private $URIs;

    /**
     * @var array
     */
    private $sessionIds;

    /**
     * @var bool|string
     */
    private static $saveFile = false;

    public static function onCommandConfigure(CommandConfigureEvent $event)
    {
        $commandName = $event->getName();
        if ($commandName === RunCommand::CMD) {
            $event
                ->setDescription("Replay URI or SessionId or Files")
                ->addOption('--request-in', '-i', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                    'Replay by request URI or request input keywords')
                ->addOption('--sessionId', '-s', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                    'Replay by session id')
                ->addOption('--save', '-S', InputOption::VALUE_OPTIONAL, 'Save replayed session to file', false)
                ->addOption('--count', '-c', InputOption::VALUE_REQUIRED, 'Replay session count', 1);

            return true;
        }

        return false;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param array $options
     * @return array
     * @throws Exception
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     * @throws ResolveInvalidParam
     * @throws \Throwable
     */
    public function resolve(InputInterface $input, OutputInterface $output, $options = [])
    {
        try {
            $sessions = parent::resolve($input, $output);
        } catch (ResolveInvalidParam $e) {
            $sessions = [];
        }

        $this->URIs = $input->getOption('request-in');
        $this->sessionIds = $input->getOption('sessionId');
        if (empty($sessions) && empty($this->URIs) && empty($this->sessionIds)) {
            throw new ResolveInvalidParam(Message::RUN_COMMAND_ELASTIC_INVALID_PARAMS);
        }

        if (false !== $input->getOption('save')) {
            // Whether save sessions to file: false true or save-dir
            self::$saveFile = $input->getOption('save') ?? true;
        }

        if ($this->URIs) {
            $size = $input->getOption('count');
            foreach ($this->URIs as $URI) {
                $sessions = array_merge($sessions, $this->queryRecentSessions($URI, $size));
            }
        }
        if ($this->sessionIds) {
            $sessions = array_merge($sessions, $this->loadSessions($this->sessionIds, true));
        }

        return $sessions;
    }

    /**
     * @param      $sessionIds
     * @param bool $forceSaveSessions2File
     *
     * @return array
     * @throws Exception
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     * @throws \Throwable
     */
    public function loadSessions($sessionIds, $forceSaveSessions2File = false)
    {
        $aDSL = [];

        // local file have high priority
        $localSessions = [];
        $recordSessions = [];
        $localSessionsDir = Container::make('sessionDir').DR;
        $output = Container::make('output');
        foreach ($sessionIds as $sessionId) {
            $sessionId = trim($sessionId);
            if (empty($sessionId)) {
                continue;
            }
            $localFile = $localSessionsDir.$sessionId.'.json';
            if (file_exists($localFile)) {
                $output->writeln("<info>Load session from cache: $localFile</info>",
                    OutputInterface::VERBOSITY_VERBOSE);
                $session = json_decode(file_get_contents($localFile), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $localSessions[] = $session;
                    continue;
                }
            }
            $oDSL = new EsDSL();
            $oDSL->sessionId($sessionId);
            $aDSL[] = $oDSL;
        }

        if (count($aDSL)) {
            $recordSessions = static::asyncQuery($aDSL);
            if ($saveDir = self::$saveFile) {
                Util::saveSessionToFile($saveDir, $recordSessions);
            } elseif ($forceSaveSessions2File) {
                Util::saveSessionToFile(true, $recordSessions);
            }
        }

        return count($localSessions) ? array_merge($recordSessions, $localSessions) : $recordSessions;
    }

    /**
     * @param string $url
     * @param int $size
     *
     * @return array
     * @throws Exception
     */
    public function queryRecentSessions(string $url, int $size)
    {
        $dsl = new EsDSL();
        $dsl->inboundRequest($url)->size($size);

        return self::queryAndSave($dsl);
    }


    /**
     * @param EsDSL $dsl
     *
     * @return array
     * @throws Exception
     * @internal param array $options
     * @internal param bool $forceSaveSession
     *
     */
    public static function queryAndSave(EsDSL $dsl)
    {
        $sessions = self::queryElastic($dsl);
        if (!empty($sessions) && $saveDir = self::$saveFile) {
            Util::saveSessionToFile($saveDir, $sessions);
        }

        return $sessions;
    }

    /**
     * @param EsDSL $dsl
     *
     * @return array
     * @throws Exception
     */
    public static function queryElastic($dsl)
    {
        $client = new Client();
        $resp = $client->post(self::getElasticSearchUrl(), [
            'headers' => [
                'kbn-xsrf:1',
            ],
            'json' => $dsl->dsl(),
        ]);

        return static::esResp2Sessions(\GuzzleHttp\json_decode($resp->getBody(), true));
    }

    /**
     * @param array $dsls []EsDSL
     *
     * @return array
     * @throws \Throwable
     */
    public static function asyncQuery(array $dsls)
    {
        $promises = [];
        $client = new Client(['base_uri' => self::getElasticSearchUrl(),]);
        foreach ($dsls as $dsl) {
            $promises[] = $client->postAsync('', [
                'headers' => [
                    'kbn-xsrf:1',
                ],
                'json' => $dsl->dsl(),
            ]);
        }

        $responses = Promise\unwrap($promises);

        $sessions = [];
        /* @var $response \GuzzleHttp\Psr7\Response */
        foreach ($responses as $response) {
            if ($response->getStatusCode() != 200) {
                continue;
            }
            try {
                $sessions = array_merge(
                    $sessions,
                    static::esResp2Sessions(\GuzzleHttp\json_decode($response->getBody(), true))
                );
            } catch (\Exception $e) {
            }
        }

        return $sessions;
    }

    private static function getElasticSearchUrl()
    {
        static $searchUrl;
        if ($searchUrl === null) {
            $url = Container::make('config')->get('php', 'elastic-search-url');
            if (empty($url)) {
                throw new RuntimeException("Can not find `elastic-search-url` in config.yml for search session from elastic."
                    . PHP_EOL . "Only replay session by file, use `-f` options");
            }
            $searchUrl = $url;
        }
        return $searchUrl;
    }

    /**
     * @param $result
     *
     * @return array
     * @throws Exception
     */
    private static function esResp2Sessions($result)
    {
        if (empty($result['hits'])) {
            throw new Exception("<error>Query ES failed: ".json_encode($result, JSON_PRETTY_PRINT)."</error>");
        }
        $hits = $result['hits']['hits'];
        if (empty($hits)) {
            return [];
        }
        $sessions = [];
        foreach ($hits as $hit) {
            $sessions[] = $hit['_source'];
        }

        return $sessions;
    }
}