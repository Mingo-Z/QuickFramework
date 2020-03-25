<?php
namespace Qf\Components\Facades;

/**
 * Class Log
 * @package Qf\Components\Facades
 *
 * @method static int debug($message)
 * @method static int notice($message)
 * @method static int warning($message)
 * @method static int error($message)
 * @method static int fatal($message)
 */
class Log extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'runlog';
    }
}