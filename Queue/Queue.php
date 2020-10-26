<?php
namespace Qf\Queue;

use Qf\Queue\Jobs\Job;

abstract class Queue
{
    protected $provider;
    protected $connectionName;

    /**
     * @param Job $job
     * @param string|null $queue
     * @return mixed
     */
    abstract public function push(Job $job, $queue = null);

    /**
     * @param string|null $queue
     * @return string|null
     */
    abstract public function rawPop($queue = null);

    /**
     * @param string|null $queue
     * @return int
     */
    abstract public function size($queue = null);

    /**
     * @param Job $job
     * @param string|null $queue
     * @return bool
     */
    abstract public function delete(Job $job, $queue = null);

    /**
     * @param Job $job
     * @param int $delay
     * @param string|null $queue
     * @return mixed
     */
    abstract function release(Job $job, $delay = 0, $queue = null);

    /**
     * @param string|null $name queue name
     * @return object queue driver object
     */
    abstract public function getQueue($name = null);

}
