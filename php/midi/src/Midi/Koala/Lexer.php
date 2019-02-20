<?php
/**
 * Simple Lexer for simulated request
 *
 * @author tanmingliang
 */

namespace Midi\Koala;

class Lexer
{
    public static function lexer(string $text)
    {
        $aUnread = $aRead = [];
        $unread = preg_replace('/[A-Za-z0-9\.\_]+/', '', $text);
        if (strlen($unread)) {
            $aUnread = str_split($unread);
        }
        $fullMatcheds = preg_match_all('/([A-Za-z0-9\.\_]+)/', $text, $matches);
        if ($fullMatcheds > 0) {
            $aRead = $matches[0];
        }

        return array_merge($aRead, $aUnread);
    }

    public static function weightVector(string $text)
    {
        $vector = [];
        $tokens = self::lexer($text);
        if (count($tokens)) {
            foreach ($tokens as $token) {
                if (isset($vector[$token])) {
                    ++$vector[$token];
                } else {
                    $vector[$token] = 1;
                }
            }
        }

        return $vector;
    }
}