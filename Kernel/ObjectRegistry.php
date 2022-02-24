<?php
namespace Qf\Kernel;

/**
 * 对象管理器，避免重复实例化的开销
 */
class ObjectRegistry
{
    protected static $objectTable = [];

    /**
     * 获取类的实例
     *
     * @param string $class 如果有namespace，则需要带上
     * @param array|null $arguments 实例化类需要的参数
     * @return object|null
     */
    public static function get($class, array $arguments = null)
    {
        $object = null;
        if (!($object = self::has($class, $arguments))) {
            $object = self::register($class, $arguments);
        }

        return $object;
    }

    protected static function has($class, array $arguments = null)
    {
        return self::$objectTable[self::getObjectKey($class, $arguments)] ?? null;
    }

    protected static function register($class, array $arguments = null)
    {
        $arguments = $arguments ?? [];
        $object = new $class(...$arguments);
        if ($object) {
            self::$objectTable[self::getObjectKey($class, $arguments)] = $object;
        }

        return $object;
    }

    protected static function hash($value)
    {
        return md5(json_encode($value));
    }

    /**
     * 销毁对象
     *
     * @param string $class
     * @param array|null $arguments
     * @return void
     */
    protected static function destroy($class, array $arguments = null)
    {
        $objectKey = self::getObjectKey($class, $arguments);
        if (isset(self::$objectTable[$objectKey])) {
            unset(self::$objectTable[$objectKey]);
        }
    }

    protected static function flushAll()
    {
        foreach (array_keys(self::$objectTable) as $objectKey) {
            unset(self::$objectTable[$objectKey]);
        }
    }

    protected static function getObjectKey($class, array $arguments = null)
    {
        return $class . ':' . self::hash($arguments);
    }

}
