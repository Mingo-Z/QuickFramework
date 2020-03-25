<?php
namespace Qf\Queue\Jobs;

use Qf\Kernel\Exception;
use Throwable;

class JobException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null, WorkerJob $job = null)
    {
        if ($job) {
            $message = sprintf('%s, id: %d, payload: %s', $message, $job->getId(), $job->encodeJob());
        }
        parent::__construct($message, $code, $previous);
    }
}
