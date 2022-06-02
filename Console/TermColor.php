<?php
namespace Qf\Console;

use Qf\Kernel\Exception;

class TermColor
{
    /**
     * 颜色名称，作为参数使用
     */
    const RED = 'RED';
    const GREEN = 'GREEN';
    const ORANGE = 'ORANGE';
    const BLACK = 'BLACK';

    /**
     * 终端颜色代码，不建议外部使用
     */
    const RED_FG_CODE = "\033[31m";
    const RED_BG_CODE = "\033[41m";
    const GREEN_FG_CODE = "\033[32m";
    const GREEN_BG_CODE = "\033[42m";
    const ORANGE_FG_CODE = "\033[33m";
    const ORANGE_BG_CODE = "\033[43m";
    const BLACK_FG_CODE = "\033[30m";
    const BLACK_BG_CODE = "\033[40m";
    const CLEAN_FG_CODE = "\033[39m";
    const CLEAN_BG_CODE = "\033[49m";

    public static function addColor($text, $colorName, $isBackground = false) {

        $constName = __CLASS__ . "::{$colorName}_" . ($isBackground ? 'BG' : 'FG') . '_CODE';
        if (!defined($constName)) {
            throw new Exception("$colorName color not supported");
        }
        $colorCode = constant($constName);
        $cleanColorCode = $isBackground ? self::CLEAN_BG_CODE : self::CLEAN_FG_CODE;

        return "{$colorCode}$text{$cleanColorCode}";
    }
}

