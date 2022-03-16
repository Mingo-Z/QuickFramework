<?php
namespace Qf\Components\Facades;

/**
 * Class Log
 * @package Qf\Components\Facades
 *
 * @method static int debug($message, $prefix = null)
 * @method static int notice($message, $prefix = null)
 * @method static int warning($message, $prefix = null)
 * @method static int error($message, $prefix = null)
 * @method static int fatal($message, $prefix = null)
 */
class Log extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'runlog';
    }
}