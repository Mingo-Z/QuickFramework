<?php
namespace Qf\Components\Log;

class LogFileWriter extends LogWriter
{
    protected $options = [
        'fileMaxSize' => 1024 * 1024 * 100,
        'fileRotateNum' => 10,
        'storagePath' => './logs/',
        'fileSuffix' => '.log',
        'filePrefix' => '',
    ];

    public function write($logMsg)
    {
        if (!is_dir($this->options['storagePath'])) {
            mkdir($this->options['storagePath'], 0700, true);
        }
        $filePrefix = $this->options['filePrefix'] . date('Y-m-d');
        $fileName = $filePrefix . $this->options['fileSuffix'];
        $fileFullPath = $this->options['storagePath'] . '/' . $fileName;
        if (is_file($fileFullPath) && filesize($fileFullPath) >= $this->options['fileMaxSize']) {
            $fileMaxIndex = $this->getFileRotateMaxIndex($filePrefix);
            $fileMaxIndex++;
            $newFileFullPath = $this->options['storagePath'] . "{$filePrefix}_{$fileMaxIndex}" .
                $this->options['fileSuffix'];
            rename($fileFullPath, $newFileFullPath);
        }

        return file_put_contents($fileFullPath, $logMsg, FILE_APPEND);
    }

    protected function getFileRotateMaxIndex($filePrefix)
    {
        $fileMaxIndex = 0;
        foreach (glob($this->options['storagePath'] . "{$filePrefix}_[0-9]+.log") as $entry) {
            $basename = pathinfo($entry, PATHINFO_BASENAME);
            $tmpArray = explode('_', $basename);
            if ($tmpArray[1] >= $fileMaxIndex) {
                $fileMaxIndex = $tmpArray[1];
            }
        }

        return $fileMaxIndex;
    }
}
