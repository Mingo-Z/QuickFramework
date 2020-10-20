<?php
namespace Qf\Kernel\Http\Middleware;

use Qf\Kernel\Application;
use Closure;

abstract class Middleware
{
    public static function handle(Application $app, Closure $next = null, $config = null)
    {
        $result = null;
        if ($next) {
            $result = $next($app);
        }

        return $result;
    }
}