<?php
namespace Qf\Components\Lock;

use Qf\Components\Provider;
use Qf\Kernel\Exception;

abstract class Lock extends Provider
{
    abstract public function readLock();

    abstract public function writeLock();

    abstract public function unlock();

    public function testLock()
    {
        throw new Exception('Require override testLock method');
    }
}