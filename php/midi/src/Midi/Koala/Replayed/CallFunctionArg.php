<?php

namespace Midi\Koala\Replayed;

class CallFunctionArg extends \ArrayObject {

    public static $SCHEMA = array(
        'disfSchemaFormatVersion' => 1003,
        'isUnion' => true,
        'classObject' => CallFunctionArg::class,
        'className' => 'Midi\Koala\Replayed\CallFunctionArg',
        'annotations' => array(),
        'fields' => array(
        ),
    );

    public function __construct($array = null)
    {
        if (!isset($array)) {
            parent::__construct(array(), \ArrayObject::ARRAY_AS_PROPS);
            return;
        }

        parent::__construct($array, \ArrayObject::ARRAY_AS_PROPS);

    }
}
