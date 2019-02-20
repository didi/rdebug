<?php

namespace Midi\Koala\Replayed;

/**
 * @property string ActionType
 * @property int OccurredAt
 * @property string ActionId
 */
class ReplayedAction extends \ArrayObject {

    public static $SCHEMA = array(
        'disfSchemaFormatVersion' => 1003,
        'isUnion' => true,
        'classObject' => ReplayedAction::class,
        'className' => 'Midi\Koala\Replayed\ReplayedAction',
        'annotations' => array(),
        'fields' => array(
            "ActionType" => array(
                "fieldId" => 3,
                "thriftType" => 'STRING',
                "isRequired" => False,
                "annotations" => array(),
            ),
            "OccurredAt" => array(
                "fieldId" => 2,
                "thriftType" => 'I64',
                "isRequired" => False,
                "annotations" => array(),
            ),
            "ActionId" => array(
                "fieldId" => 1,
                "thriftType" => 'STRING',
                "isRequired" => False,
                "annotations" => array(),
            ),
        ),
    );

    public function __construct($array = null)
    {
        if (!isset($array)) {
            parent::__construct(array(), \ArrayObject::ARRAY_AS_PROPS);
            return;
        }

        parent::__construct($array, \ArrayObject::ARRAY_AS_PROPS);

        if(isset($array["ActionType"])) {
            $this->setActionType($array["ActionType"]);
        }

        if(isset($array["OccurredAt"])) {
            $this->setOccurredAt($array["OccurredAt"]);
        }

        if(isset($array["ActionId"])) {
            $this->setActionId($array["ActionId"]);
        }

    }

    /**
     * @return string
     */
    public function getActionType()/* : string */ {
        return \Midi\Koala\Common\TypeConverter::to_string($this["ActionType"]);
    }

    /**
     * @param string $val
     */
    public function setActionType(/* string */ $val) {
        $this["ActionType"] = \Midi\Koala\Common\TypeConverter::to_string($val);
    }

    /**
     * @return int
     */
    public function getOccurredAt()/* : int */ {
        return \Midi\Koala\Common\TypeConverter::to_int($this["OccurredAt"]);
    }

    /**
     * @param int $val
     */
    public function setOccurredAt(/* int */ $val) {
        $this["OccurredAt"] = \Midi\Koala\Common\TypeConverter::to_int($val);
    }

    /**
     * @return string
     */
    public function getActionId()/* : string */ {
        return \Midi\Koala\Common\TypeConverter::to_string($this["ActionId"]);
    }

    /**
     * @param string $val
     */
    public function setActionId(/* string */ $val) {
        $this["ActionId"] = \Midi\Koala\Common\TypeConverter::to_string($val);
    }
}