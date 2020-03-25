<?php
namespace Qf\Kernel;

use Throwable;

class Exception extends \Exception
{
    const HTTP_STATUS_CODE_404 = 404;
    const HTTP_STATUS_CODE_403 = 403;

    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}