<?php
namespace Qf\Utils\Validation\Engines;

class PositiveNumEngine extends Engine
{
    public static function make($value, $argsDesc = null)
    {
        $value = (int)$value;

        return $value >= 0;
    }
}