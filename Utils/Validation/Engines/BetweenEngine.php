<?php
namespace Qf\Utils\Validation\Engines;

class BetweenEngine extends Engine
{
    public static function make($value, $argsDesc = null)
    {
        $isOk = false;
        $argArray = self::getArgArray($argsDesc);
        if (count($argArray) == 2) {
            $start = (int)$argArray[0];
            $end = (int)$argArray[1];
            if ($value >= $start && $value <= $end) {
                $isOk = true;
            }
        }

        return $isOk;
    }
}