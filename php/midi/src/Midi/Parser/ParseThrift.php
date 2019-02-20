<?php

namespace Midi\Parser;

use Thrift\Transport\TMemoryBuffer;
use Thrift\Transport\TFramedTransport;
use Thrift\Protocol\TBinaryProtocol;

class ParseThrift implements ParseInterface
{
    /**
     * 匹配后，已经出结果
     */
    public static function match($request, $response = null)
    {
        return self::parse($request);
    }

    public static function parse($request, $response = null)
    {
        $buf = new TMemoryBuffer($request);
        $transport = new TFramedTransport($buf);
        $protocol = new TBinaryProtocol($transport);

        try {
            $protocol->readMessageBegin($methodName, $messageType, $messageSeqId);
        } catch (\Exception $e) {
            return false;
        }

        return ['methodName' => $methodName, 'messageType' => $messageType, 'messageSeqId' => $messageSeqId,];
    }
}


