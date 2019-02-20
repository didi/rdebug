<?php

/**
 */
class ReturnValue extends \ArrayObject {

    public static $SCHEMA = array(
        'disfSchemaFormatVersion' => 1003,
        'isUnion' => true,
        'classObject' => ReturnValue::class,
        'className' => 'Midi\Koala\Replayed\ReturnValue',
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
