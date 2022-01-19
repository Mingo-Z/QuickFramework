<?php
namespace Qf\Components\Log;

class LogOutputWriter extends LogWriter
{
    public function write($logMsg)
    {
        $dstFile = 'php://output';
        if (isPhpCommandMode()) {
            $dstFile = 'php://stdout';
        }
        $fileDesc = fopen($dstFile, 'w');
        if ($fileDesc) {
            $writeLen = fwrite($fileDesc, $logMsg);
            fclose($fileDesc);

            return $writeLen;
        } else {
            return false;
        }
    }
}
