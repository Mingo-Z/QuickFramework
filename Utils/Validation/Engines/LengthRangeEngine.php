<?php
namespace Qf\Utils\Validation\Engines;

class LengthRangeEngine extends Engine
{
    public static function make($value, $argsDesc = null)
    {
        $isOk = false;
        $argArray = self::getArgArray($argsDesc);
        if (count($argArray) == 2) {
            $minLength = (int)$argArray[0];
            $maxLength = (int)$argArray[1];
            $length = strlen($value);
            if ($length >= $minLength && $length <= $maxLength) {
                $isOk = true;
            }
        }

        return $isOk;
    }
}