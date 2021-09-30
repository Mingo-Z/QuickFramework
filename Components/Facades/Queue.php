<?php
namespace Qf\Components\Facades;

use Qf\Components\Redis\RedisQueueProvider;

/**
 * Class Queue
 * @desc Base for Qf\Components\RedisQueueProvider
 * @package Qf\Components\Facades
 *
 * @method static int size()
 * @method static mixed rPopPush($elem)
 * @method static mixed lPush($elem)
 * @method static mixed rPush($elem)
 * @method static mixed lPop()
 * @method static mixed blPop($timeout = 0)
 * @method static mixed rPop()
 * @method static mixed brPop($timeout = 0)
 * @method static bool deleteKey($cusName = null)
 * @method static RedisQueueProvider setName($name)
 *
 */
class Queue extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'queue';
    }
}
