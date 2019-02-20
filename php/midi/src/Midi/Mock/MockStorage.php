<?php

/**
 * @author tanmingliang
 */

namespace Midi\Mock;

/**
 * Simple way mock APCu By PHP auto_prepend_file.
 */
class MockStorage
{
    /**
     * @param array $actions
     * @return string
     */
    public static function buildPatch($actions)
    {
        if (empty($actions)) {
            return '';
        }

        $patch = <<<CODE
<?php
apcu_clear_cache();

CODE;
        $patchEnd = <<<CODE
?>

CODE;
        $patchCode = '';
        $recordKeys = [];

        // the same key keep the last one
        foreach (array_reverse($actions) as $action) {
            if ($action['ActionType'] != 'ReadStorage') {
                continue;
            }
            $contents = explode("\n", $action['Content']);
            if ($contents[0] !== 'apcu_fetch') {
                continue;
            }
            $key = stripcslashes($contents[1]);
            if (isset($recordKeys[$key])) {
                continue;
            }
            $recordKeys[$key] = 1;
            $val = stripcslashes($contents[2]);
            $initCode = <<<CODE
\$v = <<<VAL
$val
VAL;
\$v = unserialize(\$v);
apcu_store('$key', \$v);

CODE;
            $patchCode = $initCode.$patchCode;
        }

        if (empty($patchCode)) {
            $sCode = $patch.$patchEnd;
        } else {
            $sCode = $patch.$patchCode.$patchEnd;
        }

        return $sCode;
    }
}
