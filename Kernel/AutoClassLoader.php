<?php
namespace Qf\Kernel;

class AutoClassLoader
{
    protected static $autoLoadBasePaths = [];

    protected static $classMap = [];

    public static function addClassMap($class, $path)
    {
        self::$classMap[$class] = $path;
    }

    public static function setAutoLoadBasePath($path)
    {
        if (is_dir($path)) {
            $path = rtrim($path, '/') . '/';
            self::$autoLoadBasePaths[] = $path;
        }
    }

    public static function loadClass($name)
    {
        if (isset(self::$classMap[$name])) {
            $classFilePath = self::$classMap[$name];
            return self::includeFile($classFilePath);
        }

        $topNs = null;
        if (preg_match('@^(App|Qf)\\\@', $name, $matches)) {
            $topNs = $matches[1];
        }
        if ($topNs) {
            if ($topNs == 'Qf') {
                $name = substr($name, 3);
                $basePath = FrameworkRootPath;
            } else {
                $name = substr($name, 4);
                $basePath = AppPath;
            }
            $relativeClassPath = str_replace('\\', '/', $name);
            $classFilePath = $basePath . $relativeClassPath . '.php';
            return self::includeFile($classFilePath);
        }

        $relativeClassPath = str_replace('\\', '/', $name);
//        $tmpArray = explode('/', $relativeClassPath);
//        $className = $tmpArray[count($tmpArray) - 1];
        foreach (self::$autoLoadBasePaths as $path) {
            $classFilePath = $path . $relativeClassPath . '.php';
            if (self::includeFile($classFilePath)) {
                return true;
            }
        }

        return false;
    }

    protected static function includeFile($file)
    {
        if (is_file($file)) {
            include $file;
            return true;
        }

        return false;
    }

    public static function init()
    {
        $classMapArray = include FrameworkConfigsPath . 'classmap.config.php';
        foreach ($classMapArray as $class => $classPath) {
            self::addClassMap($class, $classPath);
        }
        spl_autoload_register(__CLASS__ . '::loadClass');
    }
}