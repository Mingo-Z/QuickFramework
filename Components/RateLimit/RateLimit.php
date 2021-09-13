<?php
namespace Qf\Components\RateLimit;

use Qf\Components\Provider;

abstract class RateLimit extends Provider
{
    /**
     * 默认requestId
     *
     * @var string
     */
    public $defaultRequestId = 'request-default-id';

    abstract public function isAllow($requestId);

    protected function getRequestId($requestId = null)
    {
        return $requestId ?? $this->defaultRequestId;
    }
}