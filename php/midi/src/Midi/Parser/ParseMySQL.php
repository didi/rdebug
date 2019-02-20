<?php

namespace Midi\Parser;

/**
 * HEAD + BODY
 */
class ParseMySQL implements ParseInterface
{

    /**
     * 3byte len + 1byte seq
     */
    const HEAD_LEN = 4;

    /* Not MySQL TYPE, defined by midi */
    /* server 2 client hand init */
    const TYPE_S_C_HAND_INIT = 1;
    /* client 2 server login auth */
    const TYPE_C_S_AUTH = 2;
    /* server 2 client auth response */
    const TYPE_S_C_AUTH_RESP = 3;
    /* client 2 server command */
    const TYPE_C_S_COM = 4;
    /* server 2 client response command */
    const TYPE_S_C_COM_RESP = 5;
    /* client 2 server close */
    const TYPE_C_S_COM_QUIT = 6;

    /* MySQL COMMAND 范围 */
    const COM_FROM = "\x00";
    const COM_END = "\x1d";

    /* MySQL COMMAND 请求报文 部分命令 */
    const COM_QUIT = "\x01";
    const COM_INIT_DB = "\x02";
    const COM_QUERY = "\x03";
    const COM_CREATE_DB = "\x05";
    const COM_DROP_DB = "\x06";
    const COM_STMT_PREPARE = "\x16";
    const COM_STMT_EXECUTE = "\x17";
    const COM_STMT_FETCH = "\x1C";

    const AUTH_PADDING_INDEX = 13;
    const AUTH_PADDING_LEN = 23;
    const LOGIN_AUTH_PADDING = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00";
    const LOGIN_AUTH_OK = "\x07\x00\x00\x02\x00\x00\x00\x02\x00\x00\x00";
    const CLIENT_QUIT = "\x01\x00\x00\x00\x01";

    public static function match($request, $response = null)
    {
        /* text command message */
        /* REPLACE|RENAME|SHOW|DROP|EXPLAIN|DESCRIBE */
        if (preg_match('/(SELECT|INSERT|DELETE|UPDATE|SET NAMES|CREATE INDEX|CREATE TABLE) /',
                $request) && self::validMessageLen($request)) {
            return self::TYPE_C_S_COM;
        }

        if (empty($request) && self::isHandShake($response)) {
            return self::TYPE_S_C_HAND_INIT;
        }

        if (self::isLoginAuth($request) || self::isAuthOk($response)) {
            return self::TYPE_C_S_AUTH;
        }

        /* 非明文 command message */
        if (self::validMessageLen($request)) {
            if (self::isClientQuit($request)) {
                return self::TYPE_C_S_COM_QUIT;
            }
            $comType = self::getReqMessageType($request);
            if ($comType >= self::COM_FROM && $comType <= self::COM_END) {
                return self::TYPE_C_S_COM;
            }
        }

        return false;
    }

    /**
     * 登陆认证
     * 命令报文
     * 响应报文
     */
    public static function parse($request, $response = null, $type = null)
    {
        if (!isset($type)) {
            $type = self::match($request, $response);
        }

        if ($type == self::TYPE_S_C_HAND_INIT) {
            $content = '';
        } elseif ($type == self::TYPE_C_S_AUTH) {
            $content = self::getLoginUsername($request);
        } else {
            $content = self::toString($request);
        }

        return ['type' => $type, 'content' => $content,];
    }

    /**
     * Head 4 + protocol 1 + serverVersion[NULL] + threadId 3 + auth_data1 8 + pad 1 + 2 + charset 1 + status 2 + 2 + 1 + pad 10 ...
     *
     * check:
     *     message len
     *     protocol
     *     pad 1
     *     pad 10
     */
    public static function isHandShake($response)
    {
        if (!self::validMessageLen($response)) {
            return false;
        }
        $protocol = unpack("C", $response[4]);
        if (!isset($protocol[1]) && $protocol[1] !== 10) {
            return false;
        }
        $response = substr($response, 5);
        $versionPos = strpos($response, "\x00");
        //$serverVersionOriginal = substr($response, 0, $versionPos);
        $response = substr($response, $versionPos + 1);
        //$threadId = unpack("V", substr($response, 0, 4));
        //$authData1 = substr($response, 4, 8);
        $pad = $response[12];
        if ($pad !== "\x00") {
            return false;
        }
        $response = substr($response, 13);
        //$charset = unpack("C", $response[3]);
        if (substr($response, 8, 10) !== "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00") {
            return false;
        }

        return true;
    }

    /**
     * now only for mysql >= 4.1
     */
    public static function isLoginAuth($request)
    {
        if (strlen($request) <= 36 || $request[3] != "\x01") {
            return false;
        }

        /* [13, 35] == "\x00" */
        if (substr($request, self::AUTH_PADDING_INDEX, self::AUTH_PADDING_LEN) == self::LOGIN_AUTH_PADDING) {
            return true;
        }

        return false;
    }

    public static function getLoginUsername($request)
    {
        $username = '';
        $usernameIdx = self::AUTH_PADDING_INDEX + self::AUTH_PADDING_LEN;
        $pos = strpos($request, "\x00", $usernameIdx);
        if ($pos !== false) {
            $username = substr($request, $usernameIdx, $pos - $usernameIdx);
        }
        return $username;
    }

    /**
     * ok 报文 $response[4] = \x00
     */
    public static function isAuthOk($response)
    {
        if ($response === self::LOGIN_AUTH_OK) {
            return true;
        }
        return false;
    }

    public static function isClientQuit($request)
    {
        if ($request === self::CLIENT_QUIT) {
            return true;
        }
        return false;
    }

    /**
     * head = 3byte len + 1byte seq
     */
    public static function getMessageLen($request)
    {
        $d = unpack("VLen", substr($request, 0, 3) . "\x00");
        return $d['Len'];
    }

    public static function validMessageLen($request)
    {
        if (strlen($request) <= 3) {
            return false;
        }
        $bodyLen = self::getMessageLen($request);
        return strlen($request) === $bodyLen + self::HEAD_LEN ? true : false;
    }

    public static function getReqMessageType($request)
    {
        return $request[4];
    }

    public static function getReqMessage($request)
    {
        return substr($request, 5);
    }

    public static function toString($request)
    {
        $type = self::getReqMessageType($request);
        switch ($type) {
            case self::COM_INIT_DB:
                return "USE " . self::getReqMessage($request);
            case self::COM_QUERY:
                return self::getReqMessage($request);
            //case self::COM_CREATE_DB:
            //case self::COM_DROP_DB:
            //case self::COM_STMT_PREPARE:
            //case self::COM_STMT_EXECUTE:
            //case self::COM_STMT_FETCH:
            default:
                return '';
        }
    }
}
