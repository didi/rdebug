<?php

namespace Midi\Parser;

use Thrift\Transport\TMemoryBuffer;
use Thrift\Transport\TFramedTransport;
use Thrift\Protocol\TCompactProtocol;

class ParseSendSync implements ParseInterface
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
        $protocol = new TCompactProtocol($transport);

        try {
            $protocol->readMessageBegin($methodName, $messageType, $messageSeqId);
        } catch (\Exception $e) {
            return false;
        }

        if (preg_match('~(\w+).*?(\w+).*?(\w+).{3}(\S{30})~', $request, $matches)) {
            list($_, $_, $name, $id, $detail) = $matches;
            $content = "$name $id - $detail";
        } else {
            $content = substr($request, 0, 40);
        }

        return [
            'methodName'   => $methodName,
            'messageType'  => $messageType,
            'messageSeqId' => $messageSeqId,
            'content'      => $content,
        ];
    }
}


