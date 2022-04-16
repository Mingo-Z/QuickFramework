<?php
namespace Qf\Kernel\Http\Route\Rule;

use Qf\Kernel\Exception;

abstract class Rule
{
    /**
     * @var array
     */
    protected static $resolvedCache = [];

    protected static $pathHandlerConfigs = [];
    /**
     * @param array|null $options
     *
     * @return array|null
     */
    abstract protected static function resolve(array $options = null);

    public static function getRouteComponents(array $options = null)
    {
        return static::resolve($options);
    }

    /**
     * 自定义http request path路由规则
     *
     * @param string $path http request path
     * @param string|callable $handler 处理器
     * @param false $isInsensitiveCache
     * @throws Exception
     */
    public static function mappingPath($path, $handler, $isInsensitiveCache = false)
    {
        $path = $isInsensitiveCache ? strtolower($path) : $path;
        $path = rtrim($path, '/');
        if (is_string($handler) && false !== strpos($handler, ':')) {
            $items = array_filter(explode(':', $handler));
            $itemNum = count($items);
            if ($itemNum != 3 && $itemNum != 2) {
                throw new Exception("$path handler '$handler' format error, correct format: [moduleName:]controllerName:actionName");
            }
            if ($itemNum == 2) {
                $mca = [null, $items[0], $items[1]];
            }
            self::$pathHandlerConfigs[$path] = [
                'isMCA' => true, // 是否是标准的module->controller->action方式
                'handler' => $mca,
            ];
        } else {
            if (!is_callable($handler)) {
                throw new Exception("$path handler is not an executable method");
            }
            self::$pathHandlerConfigs[$path] = [
                'isMCA' => false,
                'handler' => $handler,
            ];
        }
    }

    protected static function getPathHandlerConfig($path, $isInsensitiveCache = false)
    {
        $path = $isInsensitiveCache ? strtolower($path) : $path;
        $path = '/' . trim($path, '/');

        return self::$pathHandlerConfigs[$path] ?? null;
    }
}

