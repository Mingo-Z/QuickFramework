<?php
namespace Qf\Components;

class UploadLocalFileProvider extends Provider
{
    public $allowFileExtensions = [];
    public $allowMaxFileSize = 1024 * 1024;
    public $allowMaxFileNum = 4;
    public $enableEnhanceFeCheck = false;
    public $storageBaseDirPath = './upload/attach/';
    public $mimeTypeHeaderCodes = [];

    const UPLOAD_ERR_OK = 0;
    const UPLOAD_ERR_NO_FILE = 1;
    const UPLOAD_ERR_EXTENSION = 2;
    const UPLOAD_ERR_ALLOW_SIZE = 3;
    const UPLOAD_ERR_ALLOW_NUM = 4;
    const UPLOAD_ERR_CANT_STORAGE = 5;

    protected $errno = 0;

    protected static $errors = [
        self::UPLOAD_ERR_OK => 'Ok',
        self::UPLOAD_ERR_NO_FILE => 'File does not exist',
        self::UPLOAD_ERR_EXTENSION => 'File extension is not allowed',
        self::UPLOAD_ERR_ALLOW_SIZE => 'File size exceeds limit',
        self::UPLOAD_ERR_ALLOW_NUM => 'File num exceeds limit',
        self::UPLOAD_ERR_CANT_STORAGE => 'File storage failed',
    ];

    protected function getFileStorageDirPath($fileBaseName, $nowTimestamp = null)
    {
        $nowTimestamp = $nowTimestamp ?: time();
        return sprintf('%s/%04d/%02d/%02d/%s/', $this->storageBaseDirPath,
            date('Y', $nowTimestamp), date('m', $nowTimestamp),
            date('d', $nowTimestamp), substr($fileBaseName, 8, 2));
    }

    protected static function getFileExtension($filename)
    {
        return pathinfo($filename, PATHINFO_EXTENSION);
    }

    protected function getEnhanceFileExtension($fileContent)
    {
        $extension = null;
        if ($fileContent) {
            foreach ($this->mimeTypeHeaderCodes as $key => $code) {
                if (!strncmp($code, $fileContent, strlen($code))) {
                    $extension = $key;
                    break;
                }
            }
        }
        return $extension;
    }

    protected static function getFileBaseName($fileFullPath)
    {
        return md5_file($fileFullPath);
    }

    public function saveFilesToDisk($varName)
    {
        $files = $this->getFiles($varName);
        foreach ($files as &$file) {
            $this->saveFileToDisk($file);
        }

        return $files;
    }

    public function getErrno()
    {
        return $this->errno;
    }

    /**
     * 存储上传文件
     *
     * @param array &$file UploadLocalFileProvider::getFiles返回数组的元素
     * @param null $theFileBaseName
     * @return bool
     */
    public function saveFileToDisk(array &$file, $theFileBaseName = null)
    {
        $ret = false;
        if (!$file['error'] && isset($file['tmp_name']) && is_file($file['tmp_name'])
            && isset($file['extension']) && $file['extension']) {
            $fileBaseName = $theFileBaseName ?? self::getFileBaseName($file['tmp_name']);
            $nowTimestamp = time();
            $fileStorageDirPath = $this->getFileStorageDirPath($fileBaseName, $nowTimestamp);
            if (!is_dir($fileStorageDirPath)) {
                mkdir($fileStorageDirPath, 0755, true);
            }
            $fileFullPath = $fileStorageDirPath . $fileBaseName . '.' . $file['extension'];
            if (move_uploaded_file($file['tmp_name'], $fileFullPath)) {
                $file['storagePath'] = $fileFullPath;
                $file['fileId'] = "$nowTimestamp|$fileBaseName";
                $ret = true;
            } else {
                $file['error'] = self::UPLOAD_ERR_CANT_STORAGE;
                unlink($file['tmp_name']); // 删除临时文件
            }
            if (!$file['php_error'] && $file['error'] && isset(self::$errors[$file['error']])) {
                $file['error'] = self::$errors[$file['error']];
            }
            unset($file['tmp_name']);
        }

        return $ret;
    }

    public function getFiles($varName)
    {
        $fileNum = 0;
        $files = [];
        if (isset($_FILES[$varName])) {
            if (isset($_FILES[$varName]['name'])) {
                $fileNum = is_array($_FILES[$varName]['name']) ? count($_FILES[$varName]['name']) : 1;
            }
            if ($fileNum > $this->allowMaxFileNum) {
                $this->errno = self::UPLOAD_ERR_ALLOW_NUM;
                return $files;
            }
            $fields = ['name', 'tmp_name', 'type', 'error', 'size'];
            $index = 0;
            while ($index < $fileNum) {
                foreach ($fields as $field) {
                    $files[$index][$field] = $fileNum > 1 ? $_FILES[$varName][$field][$index] : $_FILES[$varName][$field];
                }
                $index++;
            }
        }
        if ($files) {
            foreach ($files as &$file) {
                $file['php_error'] = $file['error'];
                if (!$file['error']) {
                    if ($file['size'] > $this->allowMaxFileSize) {
                        $file['error'] = self::UPLOAD_ERR_ALLOW_SIZE;
                    } else {
                        $extension = 'unknown';
                        if ($this->enableEnhanceFeCheck && $this->mimeTypeHeaderCodes) {
                            if (($partConents = getFilePartContents($file['tmp_name'], 128))) {
                                $extension = $this->getEnhanceFileExtension($partConents);
                            }
                        } else {
                            $extension = self::getFileExtension($file['name']);
                        }
                        $file['extension'] = $extension;
                        if (!$extension || !in_array($extension, $this->allowFileExtensions)) {
                            $file['error'] = self::UPLOAD_ERR_EXTENSION;
                        }
                    }
                }
            }
        }
        return $files;
    }
}

