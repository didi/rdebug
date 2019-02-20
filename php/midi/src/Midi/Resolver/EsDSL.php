<?php

/**
 * @author tanmingliang
 */
namespace Midi\Resolver;

use Midi\Container;

/**
{
    "size": %d,
    "from": %d,
    "query":{
    "bool":{
        "must":[
                {
                    "match_phrase":{ "Context":"%s" }
                },
                {
                    "match_phrase":{ "Actions.Request":"%s" }
                },
                {
                    "match_phrase":{ "Actions.Response":"%s" }
                },
                {
                    "match_phrase":{ "CallFromInbound.Request":"%s" }
                },
                {
                    "match_phrase":{ "ReturnInbound.Response":"%s" }
                },
                {
                    "range":{
                        "CallFromInbound.OccurredAt":{ "gte":%d, 'lt': %d }
                    }
                },
                {
                    "term":{ "SessionId":"%s" }
                }
            ]
        }
    },
    "sort":{
        "CallFromInbound.OccurredAt":{
            "order": "desc"
        }
    }
}
*/
class EsDSL
{
    public $size = 1;
    public $from = 0;
    public $sort = ['CallFromInbound.OccurredAt' => 'desc'];
    public $must = [];
    public $notMust = [];

    /**
     * schema
     */
    public function dsl()
    {
        return [
            'size'  => $this->size,
            'from'  => $this->from,
            'sort'  => $this->sort,
            'query' => [
                'bool' => [
                    'must'     => $this->must,
                    'must_not' => $this->notMust,
                ],
            ],
        ];
    }

    public function size($size)
    {
        $this->size = $size;
        return $this;
    }

    private function matchPhrase($key, $datas)
    {
        foreach ($datas as $data) {
            if ($data[0] === '!') {
                $this->notMust[] = ['match_phrase' => [$key => substr($data, 1),],];
            } else {
                $this->must[] = ['match_phrase' => [$key => $data,],];
            }
        }
        return $this;
    }

    public function inboundRequest($inboundRequests)
    {
        if (!is_array($inboundRequests)) {
            $inboundRequests = [$inboundRequests,];
        }
        return $this->matchPhrase('CallFromInbound.Request', $inboundRequests);
    }

    public function inboundResponse($inboundResponses)
    {
        if (!is_array($inboundResponses)) {
            $inboundResponses = [$inboundResponses,];
        }
        return $this->matchPhrase('ReturnInbound.Response', $inboundResponses);
    }

    public function outboundRequest($outboundRequests)
    {
        if (!is_array($outboundRequests)) {
            $outboundRequests = [$outboundRequests,];
        }
        return $this->matchPhrase('Actions.Request', $outboundRequests);
    }

    public function outboundResponse($outboundResponses)
    {
        if (!is_array($outboundResponses)) {
            $outboundResponses = [$outboundResponses,];
        }
        return $this->matchPhrase('Actions.Response', $outboundResponses);
    }

    public function sessionId($id)
    {
        $this->must[] = ['term' => ['SessionId' => $id,],];
        return $this;
    }

    public function host($host)
    {
        $this->must[] = ['match_phrase' => ['Context' => $host,],];
        return $this;
    }

    /**
     * @param int $begin second
     * @param int $end second
     * @return EsDSL
     */
    public function date($begin, $end)
    {
        $begin = $begin * 1000 * 1000 * 1000;
        $end = $end * 1000 * 1000 * 1000;
        $this->must[] = ['range' => ['CallFromInbound.OccurredAt' => ['gte' => $begin, 'lt' => $end,]]];
        return $this;
    }

    /**
     * @param array $params = [
     *     'inbound_request' => 'key_word',
     *     'inbound_response' => 'key_word',
     *     'outbound_request' => 'key_word',
     *     'outbound_response' => 'key_word',
     *     'apollo' => 'key_word',
     *     'size' => 1,
     *     'begin' => 20180101,
     *     'end' => 20181231,
     * ]
     *
     * @param bool $withContext
     * @return EsDSL
     * @throws \Midi\Exception\Exception
     */
    public function build($params, $withContext = true)
    {
        if ($withContext) {
            if (!empty($params['record-host'])) {
                $this->host($params['record-host']);
            } else {
                $host = Container::make('config')->get('record-host');
                if (!empty($host)) {
                    $this->host($host);
                }
            }
        }
        if (!empty($params['inbound_request'])) {
            $this->inboundRequest($params['inbound_request']);
        }
        if (!empty($params['inbound_response'])) {
            $this->inboundResponse($params['inbound_response']);
        }
        if (!empty($params['outbound_request'])) {
            $this->outboundRequest($params['outbound_request']);
        }
        if (!empty($params['outbound_response'])) {
            $this->outboundResponse($params['outbound_response']);
        }
        if (!empty($params['size'])) {
            $this->size($params['size']);
        }
        if (!empty($params['begin'])) {
            $begin = strtotime($params['begin']);
            if (empty($params['end'])) {
                $end = time();
            } else {
                $end = strtotime($params['end']);
            }
            $this->date($begin, $end);
        }

        return $this;
    }

    public function json()
    {
        return json_encode($this->dsl());
    }
}
