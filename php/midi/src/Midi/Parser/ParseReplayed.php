<?php

/**
 * @author tanmingliang
 * @author wujun
 */

namespace Midi\Parser;

use Closure;
use Midi\Midi;
use Midi\Koala\Lexer;
use Midi\Koala\Matcher;
use Midi\Koala\Koala;
use Midi\Koala\Replaying\ReplayingSession;
use Midi\Koala\Replayed\ReplayedSession;
use Midi\Koala\Replayed\CallOutbound;
use Midi\Koala\Replayed\AppendFile;

/**
 * Parse replayed session for generate report.
 */
class ParseReplayed implements ParseReplayedInterface
{
    /**
     * ACTION types
     */
    const ACTION_UNKNOWN = 1;
    // SendUDP
    const ACTION_UDP = 2;
    // CallOutbound
    const ACTION_REDIS = 4;
    const ACTION_THRIFT = 8;
    const ACTION_HTTP = 16;
    const ACTION_MYSQL = 32;
    const ACTION_KAFKA = 64;
    // AppendFile
    const ACTION_FILE = 128;

    const ACTION_ALL = (
        self::ACTION_UNKNOWN | self::ACTION_UDP | self::ACTION_REDIS
        | self::ACTION_THRIFT | self::ACTION_HTTP | self::ACTION_MYSQL
        | self::ACTION_KAFKA | self::ACTION_FILE
    );

    /**
     * Action's name
     */
    const PROTOCOL = [
        self::ACTION_UNKNOWN => 'Unknow',
        self::ACTION_UDP     => 'UDP',
        self::ACTION_REDIS   => 'Redis',
        self::ACTION_THRIFT  => 'Thrift',
        self::ACTION_HTTP    => 'HTTP',
        self::ACTION_MYSQL   => 'MySQL',
        self::ACTION_KAFKA   => 'Kafka',
        self::ACTION_FILE    => 'File',
    ];

    const NOT_FOUND_INDEX = -1;

    /**
     * Match types
     *
     * matchedIndex > 0
     */
    const MATCHED_SUCCESS = 3;
    const MATCHED_IGNORE = 2;
    const MATCHED_DIFF = 1;

    /**
     * Match types
     *
     * matchedIndex = -1
     * may new call or old call but not matched
     */
    const NOT_MATCH = -1;

    /**
     * Match types
     *
     * online - (online & test) = miss
     */
    const MISS_CALL = 0;

    const FORMAT_MYSQL_FILTER = [
        ParseMySQL::TYPE_S_C_HAND_INIT => 1,
    ];

    /**
     * @var Midi
     */
    protected $midi;

    /**
     * @var float
     */
    protected $matchThreshold;

    /**
     * @var Closure
     */
    protected $parseCallOutbound;

    /**
     * @var Closure
     */
    protected $parseSendUDP;

    /**
     * @var Closure
     */
    protected $parseAppendFile;

    /**
     * @var array
     */
    protected $bypassIndex = [];

    /**
     * @var array
     */
    protected $unknownIndex = [];

    /**
     * @var array
     */
    protected $calledOutbound = [];

    /**
     * ParseReplayed constructor.
     *
     * @param Midi $midi
     */
    public function __construct(Midi $midi)
    {
        $this->midi = $midi;
        $this->matchThreshold = $this->midi->getKoala()->getMatchThreshold();
        $this->parseSendUDP = $this->getDefaultSendUDPParser();
        $this->parseAppendFile = $this->getDefaultAppendFileParser();
        $this->parseCallOutbound = $this->getDefaultCallOutboundParser();
    }

    public function setSendUPDParser(Closure $callable)
    {
        $this->parseSendUDP = $callable;
    }

    public function setCallOutboundParser(Closure $callable)
    {
        $this->parseCallOutbound = $callable;
    }

    public function setAppendFileParser(Closure $callable)
    {
        $this->parseAppendFile = $callable;
    }

    public function doParse(ReplayingSession $replaying, ReplayedSession $replayed, $parseType = self::ACTION_ALL)
    {
        $actions = $this->parseActions($replayed->getActions(), $parseType, $getAllCalledOutbounds);

        return [
            'SessionId'      => $replayed->getSessionId(),
            'Request'        => base64_decode($replayed->getCallFromInbound()->getOriginalRequest()),
            'Response'       => base64_decode($replayed->getReturnInbound()->getResponse()),
            'OnlineResponse' => base64_decode($replaying->getReturnInbound()->getResponse()),
            'CallOutbounds'  => self::summaryCallOutbound($actions['CallOutbound'], $replaying,
                $getAllCalledOutbounds, $this->matchThreshold),
            'SendUDPs'       => $actions['SendUDP'],
            'AppendFiles'    => $actions['AppendFile'],
        ];
    }

    /**
     * @param []ReplayedAction $actions
     * @param int $parseType
     * @param array $getAllCallOutbounds
     * @return array []
     */
    public function parseActions($actions, $parseType = self::ACTION_ALL, &$getAllCallOutbounds = [])
    {
        $rows = [];
        if ($parseType === 0) {
            return $rows;
        }

        $this->bypassIndex = []; // for http 100 continue & mysql
        $this->unknownIndex = [];
        $this->calledOutbound = [];

        foreach ($actions as $actionIndex => $action) {
            if (isset($this->bypassIndex[$actionIndex])) {
                continue;
            }
            switch ($action['ActionType']) {
                case 'SendUDP':
                    if (!($parseType & self::ACTION_UDP)) {
                        continue;
                    }
                    if ($this->parseSendUDP) {
                        $parser = $this->parseSendUDP;
                        /** @see getDefaultSendUDPParser */
                        $udp = $parser($actions, $actionIndex, $parseType, $rows);
                        if (!empty($udp)) {
                            $rows['SendUDP'][$actionIndex] = $udp;
                        }
                    }
                    break;
                case 'CallOutbound':
                    if ($this->parseCallOutbound) {
                        $parser = $this->parseCallOutbound;
                        /** @see getDefaultCallOutboundParser */
                        $callOutbound = $parser($actions, $actionIndex, $parseType, $rows);
                        if (!empty($callOutbound)) {
                            $rows['CallOutbound'][$actionIndex] = $callOutbound;
                        }
                    }
                    break;
                case 'AppendFile':
                    if (!($parseType & self::ACTION_FILE)) {
                        continue;
                    }
                    if ($this->parseAppendFile) {
                        $parser = $this->parseAppendFile;
                        /** @see getDefaultAppendFileParser */
                        $rows['AppendFile'][$actionIndex] = $parser($actions, $actionIndex, $parseType, $rows);
                    }
            }
        }

        $getAllCallOutbounds = $this->calledOutbound;
        return $rows;
    }

    public function getDefaultSendUDPParser()
    {
        return null;
    }

    public function getDefaultAppendFileParser()
    {
        return function ($actions, $actionIndex, $parseType, &$rows) {
            $action = $actions[$actionIndex];
            $appendFile = new AppendFile($action);
            return [
                'Type'     => self::ACTION_FILE,
                'Protocol' => self::PROTOCOL[self::ACTION_FILE],
                'File'     => $appendFile->getFileName(),
                'Content'  => base64_decode($appendFile->getContent()),
            ];
        };
    }

    public function getDefaultCallOutboundParser()
    {
        // modify $rows direct, so not return any value
        return function ($actions, $actionIndex, $parseType, &$rows) {
            $action = $actions[$actionIndex];
            $info = self::parseCallOutbound($action, $parseType);
            if (empty($info)) {
                return null;
            }
            $key = $info['Peer'];
            $type = $info['Type'];
            $this->calledOutbound[$actionIndex] = $info['MatchedIndex'];

            if ($type == self::ACTION_HTTP && $info['Continue100']) {
                /* 100 continue */
                $nextIdx = self::get100ContinueNextIndex($actions, $actionIndex);
                if ($nextIdx !== self::NOT_FOUND_INDEX) {
                    $this->bypassIndex[$nextIdx] = true;
                    // add first action to rows and parse next 100 continue action
                    $rows['CallOutbound'][$actionIndex] = $info;

                    $continue100 = new CallOutbound($actions[$nextIdx]);
                    $this->calledOutbound[$nextIdx] = $continue100->getMatchedActionIndex();
                    $rawResp = base64_decode($continue100->getMatchedResponse());
                    $httpResp = ParseHTTP::parseResp($rawResp);
                    $info['URI'] = $info['URL'] = $info['Request'] = $info['ReqBody']
                        = base64_decode($continue100->getRequest());
                    $info['MatchedRequest'] = base64_decode($continue100->getMatchedRequest());
                    $info['MatchedResponse'] = base64_decode($continue100->getMatchedResponse());
                    $info['MatchedIndex'] = $continue100->getMatchedActionIndex();
                    $info['Similarity'] = sprintf("%.2f", 100 * $continue100->getMatchedMark());
                    $info['RespBody'] = $httpResp['body'];
                    $info['HTTPCode'] = $httpResp['httpCode'];

                    $actionIndex = $nextIdx;
                }
            } elseif ($type == self::ACTION_UNKNOWN) {
                if (!isset($this->unknownIndex[$key])) {
                    $this->unknownIndex[$key] = [];
                }
                $this->unknownIndex[$key][$actionIndex] = 1;
            }

            $rows['CallOutbound'][$actionIndex] = $info;

            // 将 相同端口号 & 临近的连续的未知的调用归类
            if ($type !== self::ACTION_UNKNOWN && isset($this->unknownIndex[$key])
                && isset($this->unknownIndex[$key][$actionIndex - 1])) {
                $fixIndex = $actionIndex - 1;
                do {
                    unset($this->unknownIndex[$key][$fixIndex]);
                    $rows['CallOutbound'][$fixIndex]['Type'] = $type;
                    $rows['CallOutbound'][$fixIndex]['Request'] = $rows[$fixIndex]['Request'];
                    $rows['CallOutbound'][$fixIndex]['MatchedResponse'] = $rows[$fixIndex]['Response'];
                    --$fixIndex;
                } while (isset($this->unknownIndex[$key][$fixIndex]));
            }
            return null;
        };
    }

    public static function parseCallOutbound($action, $parseType = self::ACTION_ALL)
    {
        if ($action instanceof \Midi\Koala\Replaying\CallOutbound || $action instanceof CallOutbound) {
            $callOutbound = $action;
        } else {
            $callOutbound = new CallOutbound($action);
        }

        $request = base64_decode($callOutbound->getRequest());
        if ($callOutbound instanceof CallOutbound) {
            $matchedRequest = base64_decode($callOutbound->getMatchedRequest());
            $response = base64_decode($callOutbound->getMatchedResponse());
            $matchedIndex = $callOutbound->getMatchedActionIndex();
            $matchedMark = $callOutbound->getMatchedMark();
            if ($matchedIndex == Koala::SIMULATED) {
                $matchedMark = 1;
            }
        } else {
            $matchedRequest = '';
            $response = base64_decode($callOutbound->getResponse());
            $matchedIndex = Koala::NOT_MATCHED;
            $matchedMark = 0;
        }
        $similarity = sprintf("%.2f", 100 * $matchedMark);

        $peer = $callOutbound->getPeer()->getIP() . ':' . $callOutbound->getPeer()->getPort();

        $ret = [
            'Type'            => self::ACTION_UNKNOWN,
            'Protocol'        => self::PROTOCOL[self::ACTION_UNKNOWN],
            'Request'         => $request,
            'MatchedRequest'  => $matchedRequest,
            'MatchedResponse' => $response,
            'MatchedIndex'    => $matchedIndex,
            'Similarity'      => $similarity,
            'Peer'            => $peer,
        ];

        if (($parseType & self::ACTION_REDIS) && ParseRedis::match($request)) {
            $redisCommands = ParseRedis::parse($request);
            foreach ($redisCommands as $redisCommand) {
                return array_merge($ret, [
                    'Type'     => self::ACTION_REDIS,
                    'Protocol' => self::PROTOCOL[self::ACTION_REDIS],
                    'Command'  => $redisCommand['command'],
                    'Args'     => $redisCommand['args'],
                ]);
            }
        } elseif (($parseType & self::ACTION_THRIFT) && false !== ($beginMessage = ParseThrift::match($request))) {
            return array_merge($ret, [
                'Type'       => self::ACTION_THRIFT,
                'Protocol'   => self::PROTOCOL[self::ACTION_THRIFT],
                'MethodName' => $beginMessage['methodName'],
            ]);
        } elseif (($parseType & self::ACTION_HTTP) && ParseHTTP::match($request, $response)) {
            $httpReq = ParseHTTP::parse($request);
            $httpResp = ParseHTTP::parseResp($response);
            $httpInfo = [
                'Type'     => self::ACTION_HTTP,
                'Protocol' => self::PROTOCOL[self::ACTION_HTTP],
                'Method'   => $httpReq['method'],
                'URI'      => $httpReq['uri'],
                'URL'      => $httpReq['url'],
                'HTTPCode' => $httpResp['httpCode'],
                'ReqBody'  => $httpReq['body'],
                'RespBody' => $httpResp['body'],
            ];
            if (isset($httpReq['continue100'])) {
                $httpInfo['Continue100'] = $httpReq['continue100'];
            }
            return array_merge($ret, $httpInfo);
        } elseif (($parseType & self::ACTION_MYSQL) && (false !== $reqType = ParseMySQL::match($request, $response))) {
            $mysqlInfo = ParseMySQL::parse($request, $response, $reqType);
            return array_merge($ret, [
                'Type'     => self::ACTION_MYSQL,
                'Protocol' => self::PROTOCOL[self::ACTION_MYSQL],
                'SQLType'  => $mysqlInfo['type'],
                'Content'  => $mysqlInfo['content'],
            ]);
        } elseif (($parseType & self::ACTION_KAFKA) && false !== ($beginMessage = ParseSendSync::match($request))) {
            return array_merge($ret, [
                'Type'       => self::ACTION_KAFKA,
                'Protocol'   => self::PROTOCOL[self::ACTION_KAFKA],
                'MethodName' => $beginMessage['methodName'],
                'Content'    => $beginMessage['content'],
            ]);
        }

        // unknown protocol
        if (!($parseType & self::ACTION_UNKNOWN)) {
            // do not match any protocol
            return [];
        }

        return $ret;
    }

    /**
     * @TODO Optimize 100 continue
     *
     * @param array $actions
     * @param int $beginIndex
     * @return int
     */
    public static function get100ContinueNextIndex(array $actions, int $beginIndex)
    {
        $reqAction = new CallOutbound($actions[$beginIndex]);
        $ip = $reqAction->getPeer()->getIP();
        $port = $reqAction->getPeer()->getPort();
        for ($i = 1; $i < 5; ++$i) {
            $action = $actions[$beginIndex + $i];
            if ($action['ActionType'] != 'CallOutbound') {
                continue;
            }
            $action = new CallOutbound($action);
            $isPeerIpEqual = $action->getPeer()->getIP() == $ip;
            $isPeerPortEqual = $action->getPeer()->getPort() == $port;
            if ($isPeerIpEqual && $isPeerPortEqual) {
                return $beginIndex + $i;
            }
        }
        return self::NOT_FOUND_INDEX;
    }

    /**
     * Fix simulated index
     *
     * Simulated calls match with missed calls.
     *
     * @param array $simulatedCalls simulated request
     * @param array $missCalls [recordIndex => recordData]
     * @param float $matchThreshold
     * @return array [offline called index => online record index]
     */
    public static function fixSimulatedIndex($simulatedCalls, $missCalls, $matchThreshold)
    {
        if (empty($simulatedCalls) || empty($missCalls)) {
            return [];
        }

        $simulatedVector = [];
        foreach ($simulatedCalls as $recordIdx => $simulated) {
            $simulatedVector[$recordIdx]['Request'] = Lexer::weightVector($simulated['Request']);
            $simulatedVector[$recordIdx]['MatchedResponse'] = Lexer::weightVector($simulated['MatchedResponse']);
        }

        $missVector = [];
        foreach ($missCalls as $recordIdx => $missCall) {
            $missVector[$recordIdx]['Request'] = Lexer::weightVector($missCall['Request']);
            $missVector[$recordIdx]['MatchedResponse'] = Lexer::weightVector($missCall['MatchedResponse']);
        }

        $fix = [];
        foreach ($simulatedCalls as $idx => $simulatedCall) {
            $scores = [];
            $maxScore = 0;
            $matchedIdx = $simulatedCall['MatchedIndex'];
            foreach ($missCalls as $recordIdx => $missCall) {
                if ($missCall['Protocol'] !== $simulatedCall['Protocol']) {
                    $scores[$recordIdx] = 0;
                    continue;
                }
                $simReq = Matcher::cosineSimilarity($simulatedVector[$idx]['Request'],
                    $missVector[$recordIdx]['Request']);
                $simResp = Matcher::cosineSimilarity($simulatedVector[$idx]['MatchedResponse'],
                    $missVector[$recordIdx]['MatchedResponse']);
                $scores[$recordIdx] = ($simReq + $simResp) / 2;

                if ($scores[$recordIdx] > $maxScore) {
                    $maxScore = $scores[$recordIdx];
                    $matchedIdx = $recordIdx;
                }
            }

            if ($maxScore >= $matchThreshold) {
                $fix[$idx] = $matchedIdx;
            }
        }
        return $fix;
    }

    /**
     * format callOutbounds
     *
     * online record - test matched
     * 1       1
     * 2       4
     * 3       (miss 3)
     * 4       2
     * 5       5
     * 6       6
     *
     * test miss 3, return: [1 3(miss add here) 4 2 5 6]
     *
     * @param array $calleds
     * @param ReplayingSession $replaying
     * @param array $allCalledOutbounds test called index MAP TO online record index
     * @param float $matchThreshold
     * @return array
     */
    public static function summaryCallOutbound($calleds, $replaying, $allCalledOutbounds, $matchThreshold)
    {
        /**
         * offline all called
         *
         * simulated without matchedIndex, maybe is a miss call
         * so get all simulated and match with miss call
         */
        $simulatedCalls = [];
        if (is_array($calleds) && count($calleds)) {
            $filterCalled = [];
            foreach ($calleds as $key => $called) {
                if ($called['MatchedIndex'] === Koala::SIMULATED) {
                    $simulatedCalls[$key] = $called;
                }
                if ($called['Type'] == self::ACTION_MYSQL && isset(self::FORMAT_MYSQL_FILTER[$called['SQLType']])) {
                    continue;
                }
                $filterCalled[$key] = $called;
            }
            $calleds = $filterCalled;
        } else {
            $calleds = [];
        }

        /**
         * get miss call, compare with online record call
         */
        $missCalls = [];
        if (empty($allCalledOutbounds)) {
            $offlineCalls = [];
        } else {
            $offlineCalls = array_flip(array_values($allCalledOutbounds));
        }
        foreach ($replaying->getCallOutbounds() as $onlineCallOutbound) {
            $recordedIndex = $onlineCallOutbound->getActionIndex();
            if (!isset($offlineCalls[$recordedIndex])) {
                $miss = self::parseCallOutbound($onlineCallOutbound);
                if ($miss['Type'] == self::ACTION_MYSQL && isset(self::FORMAT_MYSQL_FILTER[$miss['SQLType']])) {
                    continue;
                }
                $missCalls[$recordedIndex] = $miss;
            }
        }

        $fix = self::fixSimulatedIndex($simulatedCalls, $missCalls, $matchThreshold);
        if (count($fix)) {
            foreach ($fix as $offlineIdx => $recordIdx) {
                $calleds[$offlineIdx]['MatchedIndex'] = $recordIdx;
                unset($missCalls[$recordIdx]);
            }
        }

        /**
         * put miss call to offline calls
         *
         * miss -1
         * matched 1
         * not match or new request 2
         */
        $ret = [];
        foreach ($missCalls as $onlineIndex => $missCalled) {
            $missKey = 'miss-' . $onlineIndex;
            if (count($calleds) == 0) {
                $missCalled['MatchType'] = self::MISS_CALL;
                // Request store test request, MatchedRequest store online recorded request data
                $missCalled['MatchedRequest'] = $missCalled['Request'];
                unset($missCalled['Request']);
                $ret[$missKey] = $missCalled;
                continue;
            }
            do {
                $testIndex = key($calleds);
                if ($calleds[$testIndex]['MatchedIndex'] < $onlineIndex) {
                    $called = $calleds[$testIndex];
                    if ($called['MatchedIndex'] >= 0) {
                        $called['MatchType'] = self::MATCHED_SUCCESS;
                    } else {
                        $called['MatchType'] = self::NOT_MATCH;
                    }
                    $ret[$testIndex] = $called;
                    unset($calleds[$testIndex]);
                } else {
                    $missCalled['MatchType'] = self::MISS_CALL;
                    // Request store test request, MatchedRequest store online recorded request data
                    $missCalled['MatchedRequest'] = $missCalled['Request'];
                    unset($missCalled['Request']);
                    $ret[$missKey] = $missCalled;
                    continue 2;
                }
            } while (count($calleds));
        }

        if (count($calleds)) {
            foreach ($calleds as $testIndex => $called) {
                if ($called['MatchedIndex'] >= 0) {
                    $called['MatchType'] = self::MATCHED_SUCCESS;
                } else {
                    $called['MatchType'] = self::NOT_MATCH;
                }
                $ret[$testIndex] = $called;
            }
        }
        return $ret;
    }
}
