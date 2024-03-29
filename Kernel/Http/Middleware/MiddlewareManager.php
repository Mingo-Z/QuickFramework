<?php
namespace Qf\Kernel\Http\Middleware;
use Qf\Kernel\Application;
use Qf\Kernel\Exception;
use Closure;

class MiddlewareManager
{
    const TRIGGER_STAGE_HTTP_ROUTE = 'http-route';
    const TRIGGER_STAGE_HTTP_DISPATCH = 'http-dispatch';
    const TRIGGER_STAGE_HTTP_RESPONSE_BEFORE = 'http-response-before';
    const TRIGGER_STAGE_HTTP_RESPONSE_AFTER = 'http-response-after';

    protected static $registeredMiddlewareArray;

    public static function register($stageName, $middleware, $module = null, $config = null)
    {
        $module = $module ?: 'default';
        if (!is_subclass_of($middleware, Middleware::class) && !($middleware instanceof Closure)) {
            throw new Exception('Middleware must inherit Middleware or a closure');
        }
        if (!isset(self::$registeredMiddlewareArray[$stageName])) {
            self::$registeredMiddlewareArray[$stageName][$module] = [];
        }
        self::$registeredMiddlewareArray[$stageName][$module][] = [$middleware, $config];
    }

    public static function getRegisteredMiddlewareArray($stageName = null, $module = null)
    {
        $middlewareArray = [];
        if (is_null($stageName)) {
            $middlewareArray = self::$registeredMiddlewareArray;
        } elseif (isset(self::$registeredMiddlewareArray[$stageName])) {
            $middlewareArray = self::$registeredMiddlewareArray[$stageName];
            if ($module) {
                $middlewareArray = $middlewareArray[$module] ?? [];
            }
        }

        return $middlewareArray;
    }

    public static function triggerMiddleware($stageName, $module = null)
    {
        $stack = null;
        $module = $module ?: 'default';
        $gApp = Application::getApp();
        $gApp->request->setData('requestStage', $stageName);
        $middlewareArray = self::getRegisteredMiddlewareArray($stageName, $module);
        if ($middlewareArray) {
            $stack = array_reduce(array_reverse($middlewareArray), function ($next, $current) {
                return function ($app) use ($next, $current) {
                    $middleware = $current[0];
                    $middlewareConfig = $current[1];
                    if ($middleware instanceof Closure) {
                        $callable = $middleware;
                    } else {
                        $callable = [$middleware, 'handle'];
                    }
                    return call_user_func_array($callable, [$app, $next, $middlewareConfig]);
                };
            });
        }

        return $stack ? $stack($gApp) : null;
    }
}