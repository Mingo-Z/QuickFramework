<?php
namespace Qf\Components\Lock;

use Qf\Kernel\Exception;

class FileLockProvider extends Lock
{
    public $filePath;

    protected $fileHandle;

    public function init()
    {
        if (!$this->filePath || !is_file($this->filePath)) {
            throw new Exception(__CLASS__ . " filePath property not specified or file[{$this->filePath}] that are not really present");
        }
        if (($this->fileHandle = fopen($this->filePath, 'r'))) {
            throw new Exception(__CLASS__ . " {$this->filePath} file has no permission");
        }
    }

    public function readLock()
    {
        return $this->fileHandle ? flock($this->fileHandle, LOCK_SH|LOCK_NB) : false;
    }

    public function writeLock()
    {
        return $this->fileHandle ? flock($this->fileHandle, LOCK_EX|LOCK_NB) : false;
    }

    public function unlock()
    {
        return $this->fileHandle ? flock($this->fileHandle, LOCK_UN) : false;
    }

    public function __destruct()
    {
        if ($this->fileHandle) {
            if (!$this->writeLock()) {
                $this->unlock();
            }
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
    }
}
