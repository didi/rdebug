<?php
/**
 * Simple Matcher for simulated request
 *
 * @author tanmingliang
 */

namespace Midi\Koala;

class Matcher
{
    public static function cosineSimilarity($a, $b)
    {
        $prod = $aSquareSum = $bSquareSum = 0.0;

        foreach ($a as $aTerm => $aWeight) {
            if (isset($b[$aTerm])) {
                $prod += $aWeight * $b[$aTerm];
            }
            $aSquareSum += $aWeight * $aWeight;
        }
        if ($aSquareSum == 0) {
            return 0;
        }

        foreach ($b as $bWeight) {
            $bSquareSum += $bWeight * $bWeight;
        }
        if ($bSquareSum == 0) {
            return 0;
        }

        return $prod / (sqrt($aSquareSum) * sqrt($bSquareSum));
    }
}