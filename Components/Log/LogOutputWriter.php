<?php
namespace Qf\Components\Log;

class LogOutputWriter extends LogWriter
{
    public function write($logMsg)
    {
        return file_put_contents('php://output', $logMsg, FILE_APPEND);
    }
}
