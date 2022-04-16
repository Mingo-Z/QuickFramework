<?php
namespace Qf\Utils;

use Qf\Kernel\Exception;

class FileHelper
{
    public static function includeFile($file)
    {
        if (is_file($file)) {
            include $file;
        } else {
            throw new Exception("Include $file file does not exist");
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

    /**
     * 保存base64编码的文件
     *
     * @param string $data base64 content
     * @param $saveFilePath 保存路径
     * @return array|null
     */
    public static function saveBase64ToFile($data, $saveFilePath)
    {
        $file = null;
        if ($data && $saveFilePath && ($content = base64_decode($data))) {
            $saveDirPath = dirname($saveFilePath);
            if (is_dir($saveDirPath) || mkdir($saveDirPath, 0755, true)) {
                $size = self::writeContentToLocalFile($saveFilePath, $content, false);
                if ($size > 0) {
                    $file = [
                        'path' => $saveFilePath,
                        'size' => $size,
                    ];
                }
            }
        }

        return $file;
    }

    /**
     * 获取图片文件格式
     *
     * @param string $content 文件二进制数据，头部数据，不少于32Bytes
     * @return string|null 扩展名
     */
    public static function getImageFileExtension($content)
    {
        $extension = null;
        $mimeTypeHeaderCodes = [
//            'jpeg' => "\xff\xd8",
            'jpg' => "\xff\xd8",
            'png' => "\x89\x50\x4e\x47",
            'tif' => "\x49\x49\x2a\x00",
            'bmp' => "\x42\x4d\x88\xa7",
            'gif' => 'GIF',
            'webp' => 'WEBP',
            'ico' => "\x00\x00\x01\x00",
            'psd' => '8BPS',
        ];
        if (strlen($content) >= 4) {
            foreach ($mimeTypeHeaderCodes as $key => $code) {
                if (strncmp($code, $content, strlen($code)) === 0) {
                    $extension = $key;
                    break;
                }
            }
        }

        return $extension;
    }
}

