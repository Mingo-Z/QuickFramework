<?php
namespace Qf\Components\Facades;

use Qf\Kernel\Application;
use Qf\Kernel\Exception;

abstract class Facade
{

    protected static $resolvedInstance;

    protected static function getFacadeAccessor()
    {
        throw new Exception('Override getFacadeAccessor method set facade accessor name');
    }

    protected static function resolveFacadeInstance($name)
    {
        $instance = null;
        if (is_object($name)) {
            $instance = $name;
        } elseif (isset(static::$resolvedInstance[$name])) {
            $instance = static::$resolvedInstance[$name];
        } else {
            $instance = Application::getCom()->$name;
            if ($instance) {
                static::$resolvedInstance[$name] = $instance;
            } else {
                throw new Exception("Facade dependant component provider $name not configured");
            }
        }

        return $instance;
    }

    protected static function getFacadeRoot()
    {
        return static::resolveFacadeInstance(static::getFacadeAccessor());
    }

    public function __get($name)
    {
        $instance = static::getFacadeRoot();
        if (!$instance) {
            throw new Exception(static::getFacadeAccessor() . ' facade root has not been set');
        }
        return $instance->$name;
    }

    public static function __callStatic($method, array $arguments)
    {
        $instance = static::getFacadeRoot();
        if (!$instance) {
            throw new Exception(static::getFacadeAccessor() . ' facade root has not been set');
        } /*elseif (!method_exists($instance, $method)) {
            throw new Exception(get_class($instance) . "->$method method not defined");
        }*/

        return $instance->$method(...$arguments);
    }
}