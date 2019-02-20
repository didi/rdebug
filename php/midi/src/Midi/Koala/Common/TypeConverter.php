<?php

namespace Midi\Koala\Common;

class TypeConverter
{
    public static function to_string(&$val) {
        $val = strval($val);
        return $val;
    }

    public static function to_int(&$val) {
        $val = intval($val);
        return $val;
    }

    public static function to_bool(&$val) {
        $val = boolval($val);
        return $val;
    }

    public static function to_float(&$val) {
        $val = floatval($val);
        return $val;
    }

    public static function to_struct(&$val, $classObject) {

        $schema = $classObject::$SCHEMA;
        if (!isset($val)) {
            $val = new $schema['classObject']();
            return $val;
        }

        $className = $schema['className'];

        if (is_array($val) || ($val instanceof \ArrayObject)) {
            $val = new $schema['classObject']($val);
            return $val;
        }

        if (!is_a($val, $className)) {
            throw new \Exception(sprintf("%s is not %s", var_export($val, true), $className));
        }

        return $val;
    }

    public static function to_array(&$val, $convertBy, $convertArgs) {
        if (!$val) {
            return array();
        }

        if (!is_array($val)) {
            throw new \Exception(sprintf("%s is not array", var_export($val, true)));
        }

        $list = array();
        foreach ($val as $i => $element) {
            $list[$i] = call_user_func_array($convertBy, array_merge(array(&$val[$i]), $convertArgs));
        }

        return $list;
    }
}