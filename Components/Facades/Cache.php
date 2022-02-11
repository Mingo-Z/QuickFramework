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
 * @method static int lenHashTable($key)
 * @method static bool existsHashTable($key, $field)
 * @method static array getAllHashTable($key)
 * @method static mixed evalLuaCode($code, $keysNum, ...$arguments)
 *
 * operation bit methods
 * @method static int setBit(string $key, int $offset, int $value)
 * @method static int getBit(string $key, int $offset)
 * @method static int bitCount(string $key)
 */
class Cache extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'cache';
    }
}
