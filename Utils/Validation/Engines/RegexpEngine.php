<?php
namespace Qf\Utils\Validation\Engines;

class RegexpEngine extends Engine
{
    public static function make($value, $argsDesc = null)
    {
        $isOk = false;
        $argArray = self::getArgArray($argsDesc);
        if ($argArray) {
            $pattern = $argArray[0];
            $isOk = (bool)preg_match("$pattern", $value);
        }

        return $isOk;
    }
}
