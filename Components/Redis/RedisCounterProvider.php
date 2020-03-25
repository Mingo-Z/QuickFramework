<?php
namespace Qf\Components\Redis;

use Qf\Components\Provider;

/**
 *
 *基于redis的原子计数实现
 *
 * @version $Id: $
 */
class RedisCounterProvider extends Provider
{
    use RedisComTrait;

    public $name;

    public function __construct()
    {
        $this->isConnected = false;
        $this->connectTimeout = 30;
        $this->isPersistent = false;
    }

    /**
     *
     * 计数键值加$increment, 未指定时,默认为1
     *
     * @param int $increment
     * @return int
     */
    public function incr($increment = 1)
    {
        $ret = 0;

        if ($this->isConnected()) {
            $ret = $this->connection->incrBy($this->realKey(), (int)$increment);
        }

        return $ret;
    }

    /**
     *
     * 计数键值减$decrement, 未指定时,默认为1
     *
     * @param int $decrement
     * @return int
     */
    public function decr($decrement = 1)
    {
        $ret = 0;

        if ($this->isConnected()) {
            $ret = $this->connection->decrBy($this->realKey(), (int)$decrement);
        }

        return $ret;
    }
}