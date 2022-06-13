<?php
namespace Qf\Kernel;

/**
 * 运行期数据存储，支持完整数据类型
 *
 * Class RuntimeContainer
 * @package Qf\Kernel
 */
class RuntimeContainer
{
    protected static $storage = [];

    public static function set($key, $elem, $namespace = 'global')
    {
        if (!isset(self::$storage[$namespace])) {
            self::$storage[$namespace] = [];
        }
        self::$storage[$namespace][$key] = $elem;
    }

    public static function push($key, $elem, $namespace = 'global')
    {
        if (!isset(self::$storage[$namespace])) {
            self::$storage[$namespace] = [];
        }
        if (!isset(self::$storage[$namespace][$key])) {
            self::$storage[$namespace][$key] = [];
        }
        self::$storage[$namespace][$key][] = $elem;
    }

    public static function get($key, $namespace = 'global', $default = null)
    {
        $ret = $default;
        if (isset(self::$storage[$namespace]) && isset(self::$storage[$namespace][$key])) {
            $ret = self::$storage[$namespace][$key];
        }

        return $ret;
    }

    public static function getNamespaceAll($namespace)
    {
        return isset(self::$storage[$namespace]) ? self::$storage[$namespace] : null;
    }

    public static function delete($key, $namespace = 'global')
    {
        if (isset(self::$storage[$namespace]) && isset(self::$storage[$namespace][$key])) {
            unset(self::$storage[$namespace][$key]);
        }
    }

    public static function deleteNamespaceAll($namespace)
    {
        if (isset(self::$storage[$namespace])) {
            unset(self::$storage[$namespace]);
        }
    }
}
