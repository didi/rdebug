<?php

namespace Midi\Parser;

interface ParseInterface
{
    public static function match($request, $response = null);

    public static function parse($request, $response = null);
}
