<?php
namespace Qf\Utils\Image;

use Qf\Kernel\Exception;

class Processor
{
    /**
     * 图片格式
     */
    const IMAGETYPE_JPEG = IMAGETYPE_JPEG;
    const IMAGETYPE_PNG = IMAGETYPE_PNG;
    const IMAGETYPE_BMP = IMAGETYPE_BMP;
    const IMAGETYPE_GIF = IMAGETYPE_GIF;

    /**
     * 水平位置
     */
    const WATERMARK_POSITION_BOTTOM_RIGHT = 0;
    const WATERMARK_POSITION_BOTTOM_LEFT = 1;
    const WATERMARK_POSITION_TOP_RIGHT = 2;
    const WATERMARK_POSITION_TOP_LEFT = 3;

    /**
     * 裁剪图片
     *
     * @param string $srcFilename 目标文件
     * @param int $startPosX 开始坐标X
     * @param int $startPosY 开始坐标Y
     * @param int $endPosX 结束坐标X
     * @param int $endPosY 结束坐标X
     * @param string $saveFilename 保存文件名
     * @param int $saveFileType 保存文件类型
     * @return bool
     * @throws Exception
     */
    public static function crop($srcFilename, $startPosX, $startPosY, $endPosX, $endPosY, $saveFilename, $saveFileType = self::IMAGETYPE_PNG)
    {
        $ret = false;

        $dstWidth = $endPosX - $startPosX;
        $dstHeight = $endPosY - $startPosY;
        if ($dstWidth < 0 || $dstHeight < 0) {
            throw new Exception('Wrong cropped area');
        }
        $srcImage = self::getImageHandle($srcFilename);
        $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
        $isOk = imagecopyresampled($dstImage, $srcImage,
            0, 0, $startPosX, $startPosY,
            $dstWidth, $dstHeight, $dstWidth, $dstHeight);
        if ($isOk) {
            $ret = self::saveAsFile($dstImage, $saveFilename, $saveFileType);
        }
        imagedestroy($srcImage);
        imagedestroy($dstImage);

        return $ret;
    }

    /**
     * 按比例调整图片大小
     *
     * @param string $srcFilename 源文件
     * @param int $scale 调整比例
     * @param string $saveFilename 保存文件
     * @param int $saveFileType 保存文件类型
     * @return bool
     * @throws Exception
     */
    public static function resize($srcFilename, $scale = 1, $saveFilename, $saveFileType = self::IMAGETYPE_JPEG)
    {
        $ret = false;
        $srcImageMetadata = self::getImageMetadata($srcFilename);
        if ($scale <= 0) {
            throw new Exception('The resize scale must be greater than 0');
        }
        $scale = round($scale, 1);
        $newImageWidth = floor($srcImageMetadata[0] * $scale);
        $newImageHeight = floor($srcImageMetadata[1] * $scale);
        $newImage = imagecreatetruecolor($newImageWidth, $newImageHeight);
        $srcImage = self::getImageHandle($srcFilename);
        if (imagecopyresampled($newImage, $srcImage, 0, 0, 0, 0,
            $newImageWidth, $newImageHeight, $srcImageMetadata[0], $srcImageMetadata[1])) {
            $ret = self::saveAsFile($newImage, $saveFilename, $saveFileType);
        }
        imagedestroy($srcImage);
        imagedestroy($newImage);

        return $ret;
    }

    protected static function saveAsFile($image, $filename, $fileType)
    {
        $ret = false;

        $functions = [
            self::IMAGETYPE_PNG => 'imagepng',
            self::IMAGETYPE_BMP => 'imagebmp',
            self::IMAGETYPE_GIF => 'imagegif',
            self::IMAGETYPE_JPEG => 'imagejpeg',
        ];
        if (isset($functions[$fileType])) {
            $ret = $functions[$fileType]($image, $filename);
        }

        return $ret;
    }

    /**
     * 指定坐标加水印
     *
     * @param string $watermarkFilename 水印文件
     * @param string $dstFilename 目标文件
     * @param int $watermarkPosX 水印起点X轴
     * @param int $watermarkPosY 水印起点Y轴
     * @param string $saveFilename 保存文件名
     * @param int $saveFileType 保存文件类型
     * @param int $pct 水印透明度，范围：0-100，数字越大透明度越低
     * @return bool
     * @throws Exception
     */
    public static function writeImageWatermark($watermarkFilename, $dstFilename, $watermarkPosX, $watermarkPosY, $saveFilename, $saveFileType = self::IMAGETYPE_JPEG, $pct = 100)
    {
        $ret = false;

        if ($watermarkPosX >= 0 && $watermarkPosY >= 0) {
            $watermarkImage = self::getImageHandle($watermarkFilename);
            $dstImage = self::getImageHandle($dstFilename);

            $watermarkImageMetadata = self::getImageMetadata($watermarkFilename);
            $watermarkImageHeight = $watermarkImageMetadata[1];
            $watermarkImageWidth = $watermarkImageMetadata[0];
            $dstImageMetadata = self::getImageMetadata($dstFilename);
            $dstImageHeight = $dstImageMetadata[1];
            $dstImageWidth = $dstImageMetadata[0];

            $adjustWatermarkImageHeight = min($watermarkImageHeight, $dstImageHeight);
            $adjustWatermarkImageWidth = min($watermarkImageWidth, $dstImageWidth);

            $ret = imagecopymerge($dstImage, $watermarkImage, $watermarkPosX, $watermarkPosY, 0, 0,
                $adjustWatermarkImageWidth, $adjustWatermarkImageHeight, $pct);
            if ($ret) {
                self::saveAsFile($dstImage, $saveFilename, $saveFileType);
            }

            imagedestroy($watermarkImage);
            imagedestroy($dstImage);
        } else {
            throw new Exception('Coordinates can only be greater than or equal to zero');
        }

        return $ret;
    }

    /**
     * 加左上下角、右上下角位置水印封装
     *
     * @param string $watermarkFilename 水印文件
     * @param string $dstFilename 原文件
     * @param string $saveFilename 保存文件名
     * @param int $saveFileType 保存文件类型
     * @param int $position
     * @param int $pct 水印透明度，范围：0-100，数字越大透明度越低
     * @return bool
     * @throws Exception
     */
    public static function writeImageWatermarkPosition($watermarkFilename, $dstFilename, $saveFilename, $saveFileType = self::IMAGETYPE_JPEG, $position = self::WATERMARK_POSITION_BOTTOM_RIGHT, $pct = 100)
    {
        $ret = false;

        $watermarkImageMetadata = self::getImageMetadata($watermarkFilename);
        $dstImageMetadata = self::getImageMetadata($dstFilename);
        if (!$watermarkImageMetadata || !$dstImageMetadata) {
            throw new Exception('The format of the watermark or target file is not supported');
        }
        $adjustWatermarkImageWidth = min($watermarkImageMetadata[0], $dstImageMetadata[0]);
        $adjustWatermarkImageHeight = min($watermarkImageMetadata[1], $dstImageMetadata[1]);

        $diffWidth = $dstImageMetadata[0] - $adjustWatermarkImageWidth;
        $diffHeight = $dstImageMetadata[1] - $adjustWatermarkImageHeight;
        switch ($position) {
            case self::WATERMARK_POSITION_TOP_RIGHT:
                $dstPosX = $diffWidth;
                $dstPosY = 0;
                break;
            case self::WATERMARK_POSITION_TOP_LEFT:
                $dstPosX = 0;
                $dstPosY = 0;
                break;
            case self::WATERMARK_POSITION_BOTTOM_LEFT:
                $dstPosX = 0;
                $dstPosY = $diffHeight;
                break;
            default:
                $dstPosX = $diffWidth;
                $dstPosY = $diffHeight;
        }

        if (($dstPosX + $dstPosY) >= 0) {
            $ret = self::writeImageWatermark($watermarkFilename, $dstFilename, $dstPosX, $dstPosY, $saveFilename, $saveFileType, $pct);
        }

        return $ret;
    }

    /**
     * 获取图片操作符
     *
     * @param string $filename
     * @return resource|false
     * @throws Exception
     */
    protected static function getImageHandle($filename)
    {
        static $imageHandles = [];

        $functions = [
            self::IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            self::IMAGETYPE_GIF => 'imagecreatefromgif',
            self::IMAGETYPE_PNG => 'imagecreatefrompng',
            self::IMAGETYPE_BMP => 'imagecreatefrombmp',
        ];

        if (isset($imageHandles[$filename])) {
            return $imageHandles[$filename];
        }

        $imageHandle = null;
        if (!is_file($filename)) {
            throw new Exception("$filename file does not exist");
        }

        if (($metadata = self::getImageMetadata($filename)) && isset($functions[$metadata[2]])) {
            $function = $functions[$metadata[2]];
            $imageHandle = $function($filename);
        }
        if (!$imageHandle) {
            throw new Exception("$filename file format is not supported");
        }

        $imageHandles[$filename] = $imageHandle;

        return $imageHandle;
    }

    /**
     * 获取图片元数据
     *
     * @param string $filename
     * @return array|false|null
     */
    public static function getImageMetadata($filename)
    {
        static $imageMetadatas = [];

        $metadata = $imageMetadatas[$filename] ?? null;
        if (!$metadata) {
            $metadata = getimagesize($filename);
            if ($metadata) {
                $imageMetadatas[$filename] = $metadata;
            }
        }

        return $metadata;
    }
}
