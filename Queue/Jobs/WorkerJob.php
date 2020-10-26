<?php
namespace Qf\Queue\Jobs;

use Qf\Kernel\Exception;
use Qf\Queue\Queue;

abstract class WorkerJob extends Job
{
    /**
     * 最大重试次数
     *
     * @var int
     */
    public $tries;
    /**
     * 任务最长执行时间
     * @todo 基于pcntl async signal实现
     *
     * @var int
     */
    public $timeout;

    protected $queue;
    protected $connectionName;
    /**
     * @var string
     */
    protected $driverClass;

    protected $delay;

    protected function __construct(array $job = null, $queue = null)
    {
        $this->job = $job;
        $this->queue = $queue;
    }

    public function __sleep()
    {
        return [
            'tries',
            'timeout',
            'queue',
            'connectionName',
            'driverClass',
            'delay',
            'job',
        ];
    }

    /**
     * @return bool
     */
    public function work()
    {
        return $this->handle();
    }

    public function getDelay()
    {
        return $this->delay ?? 0;
    }

    /**
     *
     *
     * @return Queue
     * @throws Exception
     */
    protected function resolveDriver()
    {
        static $instances = [];

        if (!$this->connectionName || !$this->driverClass) {
            throw new Exception(static::class . "connectionName, driverClass property must be set");
        }
        $instanceId = $this->driverClass . ':' . $this->connectionName;
        if (!isset($instances[$instanceId])) {
            $instances[$instanceId] = new $this->driverClass($this->getConnectionName());
        }

        return $instances[$instanceId];
    }

    public function onQueue($name)
    {
        $this->queue = $name;
        return $this;
    }

    public function release($delay = 0)
    {
        parent::release($delay);
        $this->job['attempts']++;
        $this->setCallback();
        $this->resolveDriver()->release($this, $delay, $this->getQueue());
    }

    public function delete()
    {
        parent::delete();
        $this->resolveDriver()->delete($this, $this->getQueue());
    }

    public function onConnection($name)
    {
        $this->connectionName = $name;
        return $this;
    }

    /**
     * Instantiate an empty object without parameters for task processing
     *
     * @return WorkerJob
     */
    public static function create()
    {
        return new static(...func_get_args());
    }

    /**
     * 设置各种属性后最后调用该方法
     */
    public function dispatch()
    {
        $this->setCallback();
        return $this->resolveDriver()->release($this, $this->delay, $this->getQueue());
    }

    protected function setCallback()
    {
        $this->job = array_merge($this->getJob(),
            [
                'callback' => [
                    get_class($this),
                    serialize($this),
                ],
            ]
        );
    }

    /**
     * @param string $class
     * @param string $serializeData
     * @return WorkerJob
     * @throws Exception
     */
    protected function unserializeJob($class, $serializeData)
    {
        if (!class_exists($class)) {
            throw new Exception("$class class is not defined");
        }

        return unserialize($serializeData);
    }

    /**
     * @return WorkerJob|null
     */
    public function take()
    {
        $job = null;
        $retVal = $this->resolveDriver()->rawPop($this->getQueue());
        if ($retVal && ($rawJob = static::decodeJob($retVal))) {
            [$class, $serializeData] = $rawJob['callback'];
            $job = $this->unserializeJob($class, $serializeData);
        }

        return $job;
    }

    /**
     * @return bool
     */
    abstract protected function handle();

    public function getQueue()
    {
        return $this->queue;
    }

    public function delay($sec = 0)
    {
        $this->delay = $sec;
        return $this;
    }

    public function getConnectionName()
    {
        return $this->connectionName;
    }
}