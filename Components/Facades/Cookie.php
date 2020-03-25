<?php
namespace Qf\Components\Facades;

/**
 * Class Cookie
 * @package Qf\Components\Facades
 *
 * @method static bool set($name, $value = null, $expire = 0, $httpOnly = true)
 * @method static mixed get($name, $default =  null)
 * @method static bool del($name)
 * @method static void clearAll()
 */
class Cookie extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'cookie';
    }
}