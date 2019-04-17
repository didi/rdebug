<?php

/**
 * @author tanmingliang
 */

namespace Midi\Koala;

use stdClass;
use Midi\Midi;
use Midi\Container;
use Midi\Mock\MockDir;
use Midi\Mock\MockStorage;
use Midi\Plugin\PluginEvents;
use Midi\Plugin\Event\PostParseSessionEvent;
use Midi\Koala\Replaying\CallFromInbound;
use Midi\Koala\Replaying\CallOutbound;
use Midi\Koala\Replaying\ReplayingSession;
use Midi\Koala\Replaying\ReturnInbound;
use Midi\Parser\ParseFCGI;
use Midi\Parser\ParseHTTP;
use Midi\Reporter\Coverage;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Parse Online recorded session(record by recorder.so) TO replaying session for Koala replay.
 */
class ParseRecorded
{
    /**
     * @param Midi $midi
     * @param $recordedSession
     * @param bool $enableXdebug
     * @return ReplayingSession
     * @throws \Midi\Exception\ContainerException
     * @throws \Midi\Exception\ContainerValueNotFoundException
     * @throws \Midi\Exception\RuntimeException
     */
    public static function toReplayingSession(Midi $midi, $recordedSession, $enableXdebug = false)
    {
        $isHTTP = $midi->getKoala()->inboundIsHTTP();
        list($callFromInbound, $parsedInbound) = self::buildCallFromInbound(
            $recordedSession['CallFromInbound'],
            $isHTTP,
            $enableXdebug
        );
        $returnInbound = self::buildReturnInbound($recordedSession['ReturnInbound'], $isHTTP);
        $actions = self::buildActions($recordedSession);

        $config = $midi->getConfig();
        $event = new PostParseSessionEvent(
            PluginEvents::POST_PARSE_SESSION,
            $midi,
            $callFromInbound,
            $returnInbound,
            $actions,
            $config->get('mock-files'),
            MockDir::getRedirectDir($config),
            ['isHTTP' => $isHTTP, 'enableXdebug' => $enableXdebug, 'parsedInbound' => $parsedInbound,]
        );
        $midi->getEventDispatcher()->dispatch($event->getName(), $event);

        $replayingSession = new ReplayingSession();
        $replayingSession->setSessionId($recordedSession['SessionId']);
        $replayingSession->setCallFromInbound($event->getCallFromInbound());
        $replayingSession->setReturnInbound($event->getReturnInbound());

        if (empty($event->getMockFiles())) {
            // adapt for koala, use {} instead of [] when json_encode @TODO OPTIMIZE
            $replayingSession['MockFiles'] = new stdClass();
        } else {
            $replayingSession->setMockFiles($event->getMockFiles());
        }

        /**
         * mock dir by config
         * or modify config at pre run command event, if redirect dir is same with different sessions
         * or modify at post parse event
         */
        if (empty($event->getRedirectDirs())) {
            // adapt for koala, use {} instead of [] when json_encode @TODO OPTIMIZE
            $replayingSession['RedirectDirs'] = new stdClass();
        } else {
            $replayingSession->setRedirectDirs($event->getRedirectDirs());
        }

        $actions = $event->getActions();
        $replayingSession->setCallOutbounds($actions['CallOutbounds']);

        /**
         * Mock Storage & Code Coverage by auto_prepend_file
         * Every sessions have different prepend content
         */
        $code = $config->getPreInjectCode();
        $code .= MockStorage::buildPatch($actions['ReadStorages']);
        $code .= Coverage::buildPatch($replayingSession->getSessionId());
        file_put_contents($config->getPrependFile(), $code);

        // Koala not use AppendFiles, midi is necessary to diff recorded VS replayed append files?
        //$replayingSession->setAppendFiles($actions['AppendFiles']);
        return $replayingSession;
    }

    /**
     * @param array $recordedCallFromInbound
     * @return array
     */
    public static function callFromInbound($recordedCallFromInbound)
    {
        $fcgiReq = stripcslashes($recordedCallFromInbound['Request']);

        return ParseFCGI::parseRequest($fcgiReq);
    }

    public static function returnInbound($recordedReturnInbound)
    {
        $fcgiResp = stripcslashes($recordedReturnInbound['Response']);

        return ParseFCGI::parseResponse($fcgiResp);
    }

    private static function buildCallFromInbound(
        $recordedCallFromInbound,
        $translatesFastcgiToHttp,
        $enableXdebug = false
    ) {
        $parsedInbound = [];
        if ($translatesFastcgiToHttp) {
            $fcgi = static::callFromInbound($recordedCallFromInbound);

            $header = [];
            $requestMethod = $fcgi['params']['REQUEST_METHOD'];
            $requestUri = self::uriAddXdebugArgs($fcgi['params']['REQUEST_URI'], $cleanURI, $enableXdebug);
            $serverProtocol = $fcgi['params']['SERVER_PROTOCOL'];
            $httpReq = "${requestMethod} ${requestUri} ${serverProtocol}\r\n";
            foreach ($fcgi['params'] as $k => $v) {
                if (strpos($k, 'HTTP_') !== 0) {
                    continue;
                }
                $k = str_replace('_', '-', strtolower(substr($k, strlen('HTTP_'))));
                $httpReq .= "${k}: ${v}\r\n";
                $header [$k] = $v;
            }
            $httpReq .= "\r\n";
            $httpReq .= $fcgi['stdin'];

            $parsedInbound['URI'] = $cleanURI;
            $parsedInbound['Header'] = $header;
            $parsedInbound['Req'] = $httpReq;
        } else {
            $httpReq = stripcslashes($recordedCallFromInbound['Request']);
        }

        $callFromInbound = new CallFromInbound();
        $callFromInbound->setRequest(base64_encode($httpReq));
        $callFromInbound->setOccurredAt($recordedCallFromInbound['OccurredAt']);

        return [$callFromInbound, $parsedInbound,];
    }

    private static function buildReturnInbound($recordedReturnInbound, $translatesFastcgiToHttp): ReturnInbound
    {
        if ($translatesFastcgiToHttp) {
            $resp = static::returnInbound($recordedReturnInbound);
            if (empty($resp)) {
                Container::make('output')->writeln("<info>Response empty.</info>", OutputInterface::VERBOSITY_NORMAL);
            }
            // Because we record php-fpm traffic, so we could not get nginx's response headers
            // So, here we add a http response code and message
            $resp = "HTTP/1.1 200 OK\r\n" . $resp;
        } else {
            $resp = stripcslashes($recordedReturnInbound['Response']);
        }

        $returnInbound = new ReturnInbound();
        $returnInbound->setResponse(base64_encode($resp));

        return $returnInbound;
    }

    /**
     * fix http calloutbound
     *
     * one connection and multi actions:
     *
     * action1
     *     request: GET /path/abc?...
     *     response: (empty response)
     * action2
     *     request: (empty request)
     *     response: abc
     *
     * fixed:
     * action1
     *     request: GET /path/abc?...
     *     response: abc
     */
    private static function buildActions($recordedSession)
    {
        // for fix http CallOutbound
        $outbounds = [];
        $outboundIdx = 1;
        $identifier2Idxs = [];
        $fixOutboundsIdx = [];
        $notFixIdentifiers = [];

        $ret = [];
        foreach ($recordedSession['Actions'] as $action) {
            switch ($action['ActionType']) {
                case 'CallOutbound':
                    $idx = $outboundIdx;
                    $outbounds[$idx] = $action;
                    ++$outboundIdx;

                    $identifier = self::getIdentifier($action);
                    if (empty($identifier)) {
                        continue;
                    }
                    if (isset($identifier2Idxs[$identifier])) {
                        $identifier2Idxs[$identifier][] = $idx;
                    } else {
                        $identifier2Idxs[$identifier] = [$idx];
                    }

                    if (isset($notFixIdentifiers[$identifier])) {
                        continue;
                    }
                    if (empty($action['Request']) || empty($action['Response'])) {
                        if (isset($fixOutboundsIdx[$identifier])) {
                            $fixOutboundsIdx[$identifier][] = $idx;
                        } else {
                            // check first traffic is HTTP?
                            if (isset($identifier2Idxs[$identifier])) {
                                $firstIdx = $identifier2Idxs[$identifier][0];
                            } else {
                                $firstIdx = $idx;
                            }
                            if (!ParseHTTP::match($outbounds[$firstIdx]['Request'], $outbounds[$firstIdx]['Response'])) {
                                $notFixIdentifiers[$identifier] = 1;
                                continue;
                            }
                            $fixOutboundsIdx[$identifier] = [$idx];
                        }
                    }
                    break;
                case 'AppendFile':
                    $ret['AppendFiles'][] = $action;
                    break;
                case 'SendUDP':
                    $ret['SendUDPs'][] = $action;
                    break;
                case 'ReadStorage':
                    /** apcu */
                    $ret['ReadStorages'][] = $action;
            }
        }

        foreach ($fixOutboundsIdx as $identifier => $todoFixIdxs) {
            $c = count($todoFixIdxs);
            for ($i = 0; $i < $c;) {
                $todoFixIdx = $todoFixIdxs[$i];
                if (empty($outbounds[$todoFixIdx]['Response']) && isset($todoFixIdxs[$i + 1])) {
                    $nextIdx = $todoFixIdxs[$i + 1];
                    if (empty($outbounds[$nextIdx]["Request"])) {
                        $outbounds[$todoFixIdx]['Response'] = $outbounds[$nextIdx]["Response"];
                        unset($outbounds[$nextIdx]);
                        $i += 2;
                        continue;
                    }
                }
                ++$i;
            }
        }

        foreach ($outbounds as $idx => $action) {
            $callOutbound = new CallOutbound();
            $callOutbound->setOccurredAt($action['OccurredAt']);
            $callOutbound->setActionIndex($action['ActionIndex']);
            $callOutbound->setActionType($action['ActionType']);
            $callOutbound['Peer'] = $action['Peer'];
            $callOutbound->setResponseTime($action['ResponseTime']);
            $callOutbound->setRequest(base64_encode(stripcslashes($action['Request'])));
            $callOutbound->setResponse(base64_encode(stripcslashes($action['Response'])));
            $callOutbound->setSocketFD($action['SocketFD']);
            $ret['CallOutbounds'][] = $callOutbound;
        }

        return $ret;
    }

    /**
     * Support for xdebug
     *
     * @param string $requestUrl
     * @param string $onlyUri
     * @return string
     */
    public static function uriAddXdebugArgs($requestUrl, &$onlyUri, $enableXdebug)
    {
        $pos = strpos($requestUrl, '?');
        $onlyUri = $pos !== false ? substr($requestUrl, 0, $pos) : $requestUrl;

        if ($enableXdebug) {
            if (false !== $pos) {
                $requestUrl .= '&XDEBUG_SESSION_START';
            } else {
                $requestUrl .= '?XDEBUG_SESSION_START';
            }
        }

        return $requestUrl;
    }

    private static function getIdentifier($action) {
        if (empty($action['Peer'])) {
            return '';
        }
        $peer = $action['Peer'];
        return sprintf("%s:%d#%d", $peer['IP'], $peer['PORT'], $action['SocketFD']);
    }
}
