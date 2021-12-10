<?php
namespace Qf\Kernel;

final class ShutdownScheduler
{
    protected static $callbacks;

    public static function init()
    {
        self::$callbacks = [];
        register_shutdown_function([ShutdownScheduler::class, 'execute']);
    }

    public static function registerCallback(callable $callback, ...$arguments)
    {
        self::$callbacks[] = [$callback, $arguments];
    }

    public static function execute()
    {
        fastFinishFastCGIRequest();
        foreach (self::$callbacks as $arguments) {
            call_user_func($arguments[0], ...$arguments[1]);
        }
    }
}
