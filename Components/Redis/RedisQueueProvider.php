<?php
namespace Qf\Components\Redis;

use Qf\Components\Provider;

/**
 *
 * 基于redis的队列实现
 *
 * @version $Id: $
 */
class RedisQueueProvider extends Provider
{
    use RedisComTrait;

    public $name;

    public function __construct()
    {
        $this->isPersistent = false;
        $this->connectTimeout = 30;
        $this->isConnected = false;
    }

    /**
     * 获取指定队列大小
     *
     * @return int
     */
    public function size()
    {
        $ret = 0;

        if ($this->isConnected()) {
            $ret = $this->connection->lLen($this->realKey());
            $this->checkError();
        }
        return $ret;
    }

    /**
     * 弹出指定队列队尾元素，并且把新元素插入队头，原子操作
     *
     * @param mixed $elem
     * @return mixed
     */
    public function rPopPush($elem)
    {
        $ret = '';

        if ($this->isConnected()) {
            $ret = $this->connection->rpoplpush($this->realKey(), $this->encode($elem));
            $this->checkError();
        }

        return $ret;
    }

    /**
     * 把元素插入队头
     *
     * @param mixed $elem
     * @return bool
     */
    public function lPush($elem)
    {
        $ret = false;

        if ($this->isConnected()) {
            $ret = $this->connection->lPush($this->realKey(), $this->encode($elem));
            $this->checkError();
        }

        return $ret;
    }

    /**
     * 把元素插入队尾
     *
     * @param mixed $elem
     * @return bool
     */
    public function rPush($elem)
    {
        $ret = false;

        if ($this->isConnected()) {
            $ret = $this->connection->rPush($this->realKey(), $this->encode($elem));
            $this->checkError();
        }

        return $ret;
    }

    /**
     * 弹出队头元素
     *
     * @return mixed
     */
    public function lPop()
    {
        $ret = '';

        if ($this->isConnected()) {
            $ret = $this->decode($this->connection->lPop($this->realKey()));
            $this->checkError();
        }

        return $ret;
    }

    /**
     * 弹出队头元素,为空则阻塞等待
     *
     * @param int $timeout 阻塞等待的时间,单位:s,默认为0表示无限等待
     * @return mixed
     */
    public function blPop($timeout = 0)
    {
        $ret = '';

        if ($this->isConnected()) {
            $readTimeout = -1;
            if ($timeout > 0) {
                $readTimeout = $timeout;
            }
            $this->connection->setOption(\Redis::OPT_READ_TIMEOUT, $readTimeout); // 设置网络读取不超时,超时将会导致不会一直阻塞
            // 返回一维索引数组,0为所属队列名称,1为弹出的元素
            $response = $this->connection->blPop($this->realKey(), $timeout);
            $this->checkError();
            if ($response) {
                $ret = $this->decode($response[1]);
            }
        }

        return $ret;
    }

    /**
     * 弹出队尾元素
     *
     * @return mixed
     */
    public function rPop()
    {
        $ret = '';
        if ($this->isConnected()) {
            $ret = $this->decode($this->connection->rPop($this->realKey()));
            $this->checkError();
        }

        return $ret;
    }

    /**
     * 弹出队尾元素,为空则阻塞等待
     *
     * @param int $timeout 阻塞等待的时间,单位:s,默认为0表示无限等待
     * @return mixed
     */
    public function brPop($timeout = 0)
    {
        $ret = '';

        if ($this->isConnected()) {
            $readTimeout = -1;
            if ($timeout > 0) {
                $readTimeout = $timeout;
            }
            $this->connection->setOption(\Redis::OPT_READ_TIMEOUT, $readTimeout); // 设置网络读取不超时,超时将会导致不会一直阻塞
            // 返回一维索引数组,0为所属队列名称,1为弹出的元素
            $response = $this->connection->brPop($this->realKey(), $timeout);
            $this->checkError();
            if ($response) {
                $ret = $this->decode($response[1]);
            }

        }
        return $ret;
    }
}