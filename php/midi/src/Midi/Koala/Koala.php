<?php declare(strict_types=1);

/**
 * @author tanmingliang
 */

namespace Midi\Koala;

use Midi\Config;
use Midi\EventDispatcher\EventDispatcher;
use Midi\Container;
use Midi\Exception\KoalaNotStartException;
use Midi\Exception\KoalaRespEmptyException;
use Midi\Exception\KoalaResponseException;
use Midi\Exception\RuntimeException;
use Midi\Plugin\Event\PostReplaySessionEvent;
use Midi\Plugin\Event\PreReplaySessionEvent;
use Midi\Plugin\PluginEvents;
use Midi\Reporter\Tracer;
use Midi\Util\OS;
use Midi\Util\BM;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Process\Process;
use Midi\Koala\Replayed\ReplayedSession;
use Midi\Koala\Replaying\ReplayingSession;

class Koala
{
    /**
     * Inbound received protocol
     */
    const INBOUND_PROTOCOL_HTTP = 'HTTP';
    const INBOUND_PROTOCOL_FAST_CGI = 'FastCGI';

    /**
     * Replayed match types
     *
     * match record index
     * or, not matched
     * or, simulated
     */
    const NOT_MATCHED = -1;
    const SIMULATED = -2;

    const REPLAYER_NAME = 'koala-replayer.so';

    const CONNECT_TIMEOUT = 0.2;
    const GLOBAL_MAX_CONNECT_FAIL_COUNT = 5;
    const MAX_REPLAYED_COUNT = 51;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var EventDispatcher
     */
    protected $eventDispatcher;

    /**
     * @var int
     */
    protected $inboundPort = 5514;

    /**
     * @var int
     */
    protected $sutPort = 5515;

    /**
     * @var int
     */
    protected $outboundPort = 5516;

    /**
     * @var string
     */
    protected $inboundUrl = '';

    /**
     * @var string
     */
    protected $sessionDir = '';

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * Koala replaying default match threshold
     */
    const DEFAULT_MATCH_THRESHOLD = 0.75;

    /**
     * Koala replay match threshold
     *
     * @var float
     */
    protected $replayMatchThreshold = self::DEFAULT_MATCH_THRESHOLD;

    /**
     * @var int replayed count from koala start
     */
    private static $replayedCountFromLastStart = 0;

    /**
     * Koala constructor.
     *
     * @param Config $config
     * @param EventDispatcher $dispatcher
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    public function __construct(Config $config, EventDispatcher $dispatcher)
    {
        $this->config = $config;
        $this->eventDispatcher = $dispatcher;
        $this->init();
    }

    /**
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    protected function init()
    {
        $port = $this->config->get('koala', 'inbound-port');
        if (is_numeric($port)) {
            $this->inboundPort = $port;
        }
        $port = $this->config->get('koala', 'sut-port');
        if (is_numeric($port)) {
            $this->sutPort = $port;
        }
        $port = $this->config->get('koala', 'outbound-port');
        if (is_numeric($port)) {
            $this->outboundPort = $port;
        }

        $threshold = $this->config->get('koala', 'replay-match-threshold');
        if (is_numeric($threshold)) {
            $this->replayMatchThreshold = $threshold;
        }
        $this->inboundUrl = sprintf('http://127.0.0.1:%s/json', $this->inboundPort);
        $this->sessionDir = Container::make('sessionDir');
    }

    /**
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param ReplayingSession $session
     * @param int $tryLoop
     * @return ReplayedSession
     *
     * @throws GuzzleException
     * @throws KoalaNotStartException
     * @throws KoalaRespEmptyException
     * @throws RuntimeException
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    public function replay(ReplayingSession $session, $tryLoop = 3)
    {
        $event = new PreReplaySessionEvent($session);
        $this->eventDispatcher->dispatch(PluginEvents::PRE_REPLAY_SESSION, $event);

        $requestJson = json_encode($event->getReplayingSession(), JSON_PRETTY_PRINT);
        file_put_contents($this->sessionDir . '/session-' . $session->SessionId . '-original.json', $requestJson);

        BM::start(BM::REPLAYED_SESSION);
        $responseJson = (string)$this->doReplay($event->getReplayingSession(), $tryLoop);
        $duration = BM::stop(BM::REPLAYED_SESSION);
        file_put_contents($this->sessionDir . '/session-' . $session->SessionId . '-replayed.json', $responseJson);

        $replayedSession = new ReplayedSession(json_decode($responseJson, true));
        self::toB64($replayedSession['CallFromInbound']['OriginalRequest']);
        self::toB64($replayedSession['ReturnInbound']['OriginalResponse']);
        self::toB64($replayedSession['ReturnInbound']['Response']);
        foreach ($replayedSession['Actions'] as &$action) {
            switch ($action['ActionType']) {
                case 'SendUDP':
                    self::toB64($action['Content']);
                    break;
                case 'AppendFile':
                    self::toB64($action['Content']);
                    break;
                case 'ReturnInbound':
                    self::toB64($action['OriginalResponse']);
                    self::toB64($action['Response']);
                    break;
                case 'CallOutbound':
                    self::toB64($action['Request']);
                    self::toB64($action['MatchedRequest']);
                    self::toB64($action['MatchedResponse']);
                    break;
            }
        }

        $event = new PostReplaySessionEvent($replayedSession, ['duration' => $duration,]);
        $this->eventDispatcher->dispatch(PluginEvents::POST_REPLAY_SESSION, $event);
        return $event->getReplayedSession();
    }

    /**
     * @param ReplayingSession $session
     * @param int $tryLoop
     * @return \Psr\Http\Message\StreamInterface
     * @throws KoalaNotStartException
     * @throws RuntimeException
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     * @throws KoalaRespEmptyException
     * @throws GuzzleException
     */
    protected function doReplay(ReplayingSession $session, int $tryLoop = 3)
    {
        ++self::$replayedCountFromLastStart;
        if (self::$replayedCountFromLastStart > self::MAX_REPLAYED_COUNT) {
            self::restart();
        }

        /* koala replay session */
        $client = new Client();
        $options = [RequestOptions::JSON => $session,];

        $tryLoop = $tryLoop > 2 ? $tryLoop : 2;
        for ($i = 1; $i <= $tryLoop; ++$i) {
            try {
                $response = $client->request('post', $this->inboundUrl, $options);

                if ($response->getStatusCode() !== 200) {
                    if ($i == $tryLoop) {
                        throw new KoalaResponseException('<error>Koala response code not 200: '
                            . $response->getReasonPhrase() . '</error>');
                    }
                    $this->output->writeln("<error>Seems Koala response code not 200, retry ...</error>");
                    continue;
                }

                $responseJson = $response->getBody();
                if ($responseJson->getSize() == 0) {
                    if ($i == $tryLoop) {
                        throw new KoalaRespEmptyException('<error>Koala response empty: '
                            . $response->getReasonPhrase() . '</error>');
                    }
                    $this->output->writeln("<error>Seems Koala response empty, retry ...</error>");
                    continue;
                }

                break; // everything ok
            } catch (Exception $e) {
                if ($e instanceof ConnectException || $e instanceof KoalaResponseException) {
                    if ($i == $tryLoop - 1) {
                        self::restart(); /* last try */
                        continue;
                    } elseif ($i === $tryLoop) { /* after multi restart, still fail */
                        throw new KoalaNotStartException("<error>Koala connect fail.</error>");
                    }

                    $this->output->writeln("<error>Seems Koala connect failed, retry ...</error>");
                    $options[RequestOptions::CONNECT_TIMEOUT] = self::CONNECT_TIMEOUT * ($tryLoop + 1); // increase timeout
                    continue;
                } elseif ($e instanceof KoalaRespEmptyException) {
                    throw $e; // outside will skip
                } else {
                    throw new RuntimeException("Koala Runtime Exception: " . $e->getMessage(), $e->getCode(), $e);
                }
            }
        }

        return $responseJson;
    }

    /**
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     * @throws KoalaNotStartException
     */
    private function restart()
    {
        static $restartCount = 0;
        static $replayerCommand;
        if ($replayerCommand === null) {
            $replayerCommand = Container::make('app')->find('replayer');
        }

        ++$restartCount;
        $this->output->writeln("<info>Restart Koala: $restartCount ...</info>",
            OutputInterface::VERBOSITY_VERY_VERBOSE);
        $replayerCommand->run(new ArrayInput(['--fast-start' => 1,]), $this->output);

        if (!$this->isStartUp()) {
            throw new KoalaNotStartException("<error>Restart koala, but can not start up.</error>");
        }

        self::$replayedCountFromLastStart = 0;
    }

    /**
     * Return replay threshold
     *
     * @return float
     */
    public function getMatchThreshold()
    {
        return $this->replayMatchThreshold;
    }

    /**
     * @return array
     * @throws RuntimeException
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     */
    protected function getInjectSoEnv()
    {
        $koalaReplayer = Container::make('replayerDir') . DR . self::REPLAYER_NAME;
        $env = [];

        if (OS::isMacOs()) {
            $env['DYLD_INSERT_LIBRARIES'] = $koalaReplayer . ':/usr/lib/libcurl.dylib';
            $env['DYLD_FORCE_FLAT_NAMESPACE'] = 'y';
        } elseif (OS::isLinux()) {
            // TODO optimize
            $env['LD_PRELOAD'] = $koalaReplayer;
            $extDir = ini_get('extension_dir');
            $curlSO = $extDir . DR . 'curl.so';
            if (file_exists($curlSO)) {
                $env['LD_PRELOAD'] .= " $curlSO";
            }
            $socketSO = $extDir . DR . 'sockets.so';
            if (file_exists($socketSO)) {
                $env['LD_PRELOAD'] .= " $socketSO";
            }
        } else {
            throw new RuntimeException(sprintf("Sorry, not support %s system", PHP_OS));
        }
        return $env;
    }

    /**
     * @param array $options
     * @return string
     * @throws RuntimeException
     * @throws \Midi\Exception\ContainerException
     */
    public function getStartCMD($options)
    {
        $env = $this->getInjectSoEnv();

        $env['LC_CTYPE'] = 'C';
        $env['KOALA_INBOUND_ADDR'] = ':' . $this->inboundPort;
        $env['KOALA_SUT_ADDR'] = '127.0.0.1:' . $this->sutPort;
        $env['KOALA_OUTBOUND_ADDR'] = '127.0.0.1:' . $this->outboundPort;
        $env['KOALA_REPLAYING_MATCH_STRATEGY'] = $options['match'];
        $env['KOALA_REPLAYING_MATCH_THRESHOLD'] = $this->replayMatchThreshold;

        if (isset($options['KOALA_LOG_LEVEL'])) {
            $env['KOALA_LOG_LEVEL'] = $options['KOALA_LOG_LEVEL'];
        }
        if (isset($options['KOALA_LOG_FILE'])) {
            $env['KOALA_LOG_FILE'] = $options['KOALA_LOG_FILE'];
        }
        if (isset($options['KOALA_INBOUND_READ_TIMEOUT'])) {
            $env['KOALA_INBOUND_READ_TIMEOUT'] = $options['KOALA_INBOUND_READ_TIMEOUT'];
        }
        if (isset($options['KOALA_GC_GLOBAL_STATUS_TIMEOUT'])) {
            $env['KOALA_GC_GLOBAL_STATUS_TIMEOUT'] = $options['KOALA_GC_GLOBAL_STATUS_TIMEOUT'];
        }

        $bypassPorts = [];
        $xdebugPort = ini_get('xdebug.remote_port');
        if ($xdebugPort !== false) {
            $bypassPorts = [$xdebugPort];
        }
        $configBypassPorts = $this->config->get('koala', 'bypass-ports') ?? [];
        $bypassPorts = array_unique(array_merge($bypassPorts, $configBypassPorts));
        if (!empty($bypassPorts)) {
            $env['KOALA_OUTBOUND_BYPASS_PORT'] = implode(',', $bypassPorts);
        }

        $envStr = '';
        foreach ($env as $k => $v) {
            $envStr .= $k . '="' . $v . '" ';
        }
        $iniStr = $this->buildCMDIniDefine($options);
        return $envStr . $this->getServerCmd() . $iniStr;
    }

    /**
     * @return string
     */
    public function getServerCmd()
    {
        return 'php -S 127.0.0.1:' . $this->sutPort;
    }

    /**
     * @return array
     */
    public function getPorts()
    {
        return [$this->inboundPort, $this->sutPort, $this->outboundPort];
    }

    public function getOutboundPort()
    {
        return $this->outboundPort;
    }

    /**
     * @param array $options
     * @return string
     */
    public function buildCmdIniDefine(array $options)
    {
        $ini = [];
        if ($options['trace']) {
            $ini = Tracer::getTraceEnv();
        }

        $confIni = $this->config->get('php', 'koala-php-ini');
        $ini = array_merge($ini, $confIni);

        $ini['auto_prepend_file'] = $this->config->getPrependFile();

        $envStr = '';
        foreach ($ini as $key => $value) {
            if (is_string($value)) {
                $envStr .= ' -d ' . $key . '="' . $value . '"';
            } else {
                $envStr .= ' -d ' . $key . '=' . $value;
            }
        }
        return $envStr;
    }

    /**
     * Ping koala is startup
     *
     * @param int $timeout
     * @return bool
     */
    public function isStartUp($timeout = 5)
    {
        $timeout = abs($timeout);
        $begin = time();
        $process4 = new Process('nc -vz 127.0.0.1 ' . $this->inboundPort);
        $process5 = new Process('nc -vz 127.0.0.1 ' . $this->sutPort);
        $checkList = [4 => $process4, 5 => $process5,];
        $status = [4 => false, 5 => false,];

        while (time() - $begin < $timeout) {
            foreach ($checkList as $k => $process) {
                $process->start();
                foreach ($process as $resp) {
                    $this->output->writeln("<info>Ping Koala: <comment>" . trim($resp) . "</comment></info>",
                        OutputInterface::VERBOSITY_VERY_VERBOSE
                    );
                    if (strpos($resp, 'succeeded') !== false) {
                        $status[$k] = true;
                        unset($checkList[$k]);
                        break;
                    }
                }
            }
            if ($status[4] && $status[5]) {
                return true;
            }
            usleep(50000);
        }
        return false;
    }

    public function inboundIsHTTP()
    {
        $protocol = strtolower($this->config->get('koala', 'inbound-protocol'));
        return $protocol === strtolower(Koala::INBOUND_PROTOCOL_HTTP);
    }

    private static function toB64(&$var)
    {
        $var = base64_encode(stripcslashes($var));
    }
}
