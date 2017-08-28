<?php
/**
 * Created by PhpStorm.
 * User: dekamaru
 * Date: 12/15/16
 * Time: 6:36 PM
 */

namespace App\Util;

class Colorize
{
    /**
     * Font colors
     */
    const DEFAULT = 39;
    const BLACK = 30;
    const RED = 31;
    const GREEN = 32;
    const YELLOW = 33;
    const BLUE = 34;
    const MAGENTA = 35;
    const CYAN = 36;
    const LIGHT_GRAY = 37;
    const DARK_GRAY = 90;
    const LIGHT_RED = 91;
    const LIGHT_GREEN = 92;
    const LIGHT_YELLOW = 93;
    const LIGHT_BLUE = 94;
    const LIGHT_MAGENTA = 95;
    const LIGHT_CYAN = 96;
    const WHITE = 97;

    /**
     * Background colors
     */
    const BG_DEFAULT = 49;
    const BG_BLACK = 40;
    const BG_RED = 41;
    const BG_GREEN = 42;
    const BG_YELLOW = 43;
    const BG_BLUE = 44;
    const BG_MAGENTA = 45;
    const BG_CYAN = 46;
    const BG_LIGHT_GRAY = 47;
    const BG_DARK_GRAY = 100;
    const BG_LIGHT_RED = 101;
    const BG_LIGHT_GREEN = 102;
    const BG_LIGHT_YELLOW = 103;
    const BG_LIGHT_BLUE = 104;
    const BG_LIGHT_MAGENTA = 105;
    const BG_LIGHT_CYAN = 106;
    const BG_WHITE = 107;

    /**
     * Font attributes
     */
    const BOLD = 1;
    const DIM = 2;
    const UNDERLINED = 4;
    const BLINK = 5;
    const INVERT = 7;
    const HIDDEN = 8;

    public static function out($text, $color = Colorize::DEFAULT, $background = Colorize::BG_DEFAULT, $style = array()) {
        return self::generateString($text, $color, $background, $style);
    }

    private static function generateString($text, $color, $background, $style) {
        $out = "\033[";
        if (count($style) > 0) {
            $out .= implode(';', $style);
        }
        $out .= implode(';', array($background, $color));
        $out .= "m" . $text ."\033[0m";
        return $out;
    }

}