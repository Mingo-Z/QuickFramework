<?php
namespace Qf\Components\Facades;

/**
 * Class Cache
 * @package Qf\Components\Facades
 *
 * @method static bool set($key, $value, $expire = null)
 * @method static mixed get($key)
 * @method static bool delete($key)
 *
 * RedisCacheProvider
 * @method static bool setHashTable($key, $field, $value)
 * @method static bool delHashTable($key, $field)
 * @method static string getHashTable($key, $field)
 * @method static mixed evalLuaCode($code, $keysNum, ...$arguments)
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'cache';
    }
}