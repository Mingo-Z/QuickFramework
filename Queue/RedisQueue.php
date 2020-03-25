<?php
namespace Qf\Queue;

use Qf\Kernel\Exception;
use Qf\Queue\Jobs\Job;

class RedisQueue extends Queue
{
    public function __construct($connectionName)
    {
        $this->connectionName = $connectionName;
        $this->provider = getComponent($connectionName);
        if (!$this->provider) {
            throw new Exception(__CLASS__ . " connectionName $connectionName not configured in file components.config.php");
        }
    }

    public function push(Job $job, $queue = null)
    {
        return $this->getQueue($queue)->add($job->encodeJob(), time() - 1);
    }

    public function getQueue($name = null)
    {
        if ($name) {
            $this->provider->name = $name;
        }
        return $this->provider;
    }

    public function release(Job $job, $delay = 0, $queue = null)
    {
        return $this->getQueue($queue)->add($job->encodeJob(), time() + $delay);
    }

    /**
     * 如果要删除，不能修改作业的任何属性
     *
     * @param Job $job
     * @param string|null $queue
     * @return bool
     */
    public function delete(Job $job, $queue = null)
    {
        return $this->getQueue($queue)->del($job->encodeJob());
    }

    /**
     * @param null $queue
     * @param bool $isBlocking
     * @return string|null
     */
    public function rawPop($queue = null)
    {
        $retVal = $this->getQueue($queue)->listElems(0, time(), 0, 1, true);
        return $retVal ? $retVal[0] : null;
    }

    public function size($queue = null)
    {
        return $this->getQueue($queue)->count();
    }
}