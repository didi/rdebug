<?php

namespace Midi\Koala\Common;

/**
 * Class AbstractEnum
 *
 * Encapsulates (as best as PHP can, Enums as a type)
 * Extend the class and define constants for the valid values
 *
 * This class basically mimics SPLEnum from the SPL-Types PECL
 * extension except in vanilla PHP so no extension required.
 *
 * Example:
 *
 * class MyEnum extends DisfAbstractEnum
 * {
 *   const FOO = 'foo';
 *   const BAR = 'bar';
 * }
 *
 * $enum = new MyEnum(MyEnum::FOO);
 *
 * $enum = new MyEnum(99); //throws exception because not valid value
 */
abstract class AbstractEnum {

    /**
     * @var mixed
     */
    protected $value;

    /**
     * @param mixed $value
     * @throws \ReflectionException
     */
    public function __construct($value)
    {
        $this->setValue($value);
    }

    /**
     * @param mixed $value
     * @throws \ReflectionException
     */
    public function setValue($value)
    {
        if (!static::isValid($value)) {
            throw new \InvalidArgumentException(sprintf("Invalid enumeration %s for Enum %s", $value, get_class($this)));
        }
        $this->value = $value;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Check if the set value on this enum is a valid value for the enum
     * @param $value
     * @return boolean
     * @throws \ReflectionException
     */
    public static function isValid($value)
    {
        if(!in_array($value, static::validValues(), true)) {
            return false;
        }
        return true;
    }


    /**
     * Get the valid values for this enum
     * Defaults to constants you define in your subclass
     * override to provide custom functionality
     * @param bool $assoc
     * @return array
     * @throws \ReflectionException
     */
    public static function validValues($assoc = false)
    {
        $r = new \ReflectionClass(get_called_class());
        if($assoc == true) {
            return $r->getConstants();
        } else {
            return array_values($r->getConstants());
        }
    }
}
