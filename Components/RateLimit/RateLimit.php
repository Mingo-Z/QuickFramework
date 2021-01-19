<?php
namespace Qf\Components\RateLimit;

use Qf\Components\Provider;

abstract class RateLimit extends Provider
{
    abstract public function isAllow($requestId);
}