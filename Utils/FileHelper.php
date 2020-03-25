<?php
namespace Qf\Utils;

use Qf\Kernel\Exception;

class FileHelper
{
    protected static $loadedFiles = [];

    public static function includeFile($file)
    {
        $file = realpath($file);
        $fileId = md5($file);
        if (!isset(self::$loadedFiles[$fileId])) {
            if (is_file($file)) {
                require_once $file;
                self::$loadedFiles[$fileId] = $file;
            } else {
                throw new Exception("Include $file file does not exist");
            }
        }
    }

    public static function includeFiles(array $files)
    {
        foreach ($files as $file) {
            self::includeFile($file);
        }
    }

    public static function createFile($file)
    {
        return touch($file);
    }

    public static function writeContentToLocalFile($file, $content, $isAppend = true)
    {
        $flags = $isAppend ? FILE_APPEND : 0;
        return file_put_contents($file, $content, $flags);
    }

    public static function readLocalFileN($file, $n = null, $seek = SEEK_SET, $offset = null)
    {
        $offset = $offset ?? 0;
        $fileObject =new \SplFileObject($file, 'rb');
        $fileObject->fseek((int)$offset, $seek);
        $n = $n ?? $fileObject->getSize();
        return $fileObject->fread((int)$n);
    }
}
