<?php
namespace Qf\Utils\Validation\Engines;

abstract class Engine
{
    abstract public static function make($value, $argsDesc = null);

    protected static function getArgArray($argsDesc)
    {
        $argArray = null;
        if ($argsDesc) {
            $argArray = array_filter(array_map('trim', explode(',', $argsDesc)));
        }

        return $argArray;
    }
}