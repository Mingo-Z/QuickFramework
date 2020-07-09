<?php
namespace Qf\Kernel\Http;

use Qf\Kernel\Exception;
use Throwable;

class HttpException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
