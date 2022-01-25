<?php
namespace Qf\Components\Facades;

use Qf\Components\Redis\RedisCounterProvider;

/**
 * Class Cache
 * @package Qf\Components\Facades
 *
 * @method static int incr($increment = 1)
 * @method static int decr($decrement = 1)
 * @method static RedisCounterProvider setName($name)
 *
 */

class Counter extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'counter';
    }
}
