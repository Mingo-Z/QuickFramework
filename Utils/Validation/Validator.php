<?php
namespace Qf\Utils\Validation;

use Qf\Utils\Validation\Engines\BetweenEngine;
use Qf\Utils\Validation\Engines\Engine;
use Qf\Utils\Validation\Engines\LengthRangeEngine;
use Qf\Utils\Validation\Engines\RegexpEngine;

class Validator
{
    protected static $predefineEngines = [
        'regexp' => RegexpEngine::class,
        'length_range' => LengthRangeEngine::class,
        'between' => BetweenEngine::class,
        'commons' => [
            'email', 'mobile', 'alpha',
            'alpha_num', 'password', 'digit',
            'internal_area_code', 'currency',
            'positive_num',
        ]
    ];

    protected static $registeredEngines = [];

    public static function registerEngine($name, Engine $engine)
    {
        self::$registeredEngines[$name] = $engine;
    }

    public static function make(array $values, array $rules)
    {
        $errors = [];
        foreach ($rules as $key => $rule) {
            $engines = self::getEngines($rule);
            $isRequired = isset($engines['required']);
            $isBail = isset($engines['bail']);
            if ($isRequired && (!isset($values[$key]) || !$values[$key])) {
                $errors[$key][] = 'required';
                if ($isBail) {
                    break;
                }
            }
            $value = $values[$key] ?? '';
            foreach ($engines as $name => $argsDesc) {
                $isOk = self::makeEngine($name, $value, $argsDesc);
                if (!$isOk) {
                    $errors[$key][] = $name;
                    if ($isBail) {
                        break 2;
                    }
                }
            }
        }

        return $errors;
    }


    protected static function getEngines($rule)
    {
        $engines = [];
        if ($rule) {
            foreach (explode('|', $rule) as $engineDesc) {
                $engineDescArray = explode(':', $engineDesc);
                $engineName = $engineDescArray[0] ?? null;
                $argsDesc = $engineDescArray[1] ?? '';
                if ($engineName) {
                    $engines[$engineName] = $argsDesc;
                }
            }
        }

        return $engines;
    }

    public static function makeEngine($name, $value, $argsDesc = null)
    {
        $method = 'make';
        $engineClass = self::$registeredEngines[$name] ?? self::$predefineEngines[$name] ?? null;
        if (in_array($name, self::$predefineEngines['commons'])) {
            $engineClass = Validation::class;
            $method = $name;
        }

        $isOk = true;
        if ($engineClass) {
            $isOk = call_user_func([$engineClass, $method], $value, $argsDesc);
        }

        return $isOk;
    }

}
