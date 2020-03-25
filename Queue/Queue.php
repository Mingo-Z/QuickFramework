<?php
namespace Qf\Queue;

use Qf\Queue\Jobs\Job;

abstract class Queue
{
    protected $provider;
    protected $connectionName;

    abstract public function push(Job $job, $queue = null);

    abstract public function rawPop($queue = null);

    abstract public function size($queue = null);

    abstract public function delete(Job $job, $queue = null);

    public function release(Job $job, $delay = 0, $queue = null)
    {

    }

    abstract public function getQueue($name = null);

}