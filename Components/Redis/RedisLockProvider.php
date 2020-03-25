<?php
namespace Qf\Components\Redis;

use Qf\Components\Provider;

/**
 *
 * 基于redis的分布式网络锁实现
 *
 * @version $Id: $
 */
class RedisLockProvider extends Provider
{
    use RedisComTrait;

    public function __construct() {
        $this->isConnected = false;
        $this->isPersistent = false;
        $this->connectTimeout = 30;
    }
    
    /**
     * 加锁
     * 
     * @param string $name 锁名
     * @param int $wait 加锁等待时间，单位：s
     * @param int $expire 锁有效时间，用于锁自动过期，防止死锁，单位：s
     * @return boolean
     */
    public function lock($name, $wait = 3, $expire = 10)
    {
        $res = false;

        if ($this->isConnected()) {
            $toTimestamp = time() + $wait;
            do {
                $exTimestamp = time() + $expire;
                $lockName = $this->realKey($name);
                $res = $this->connection->set($lockName, $exTimestamp, array('nx', 'ex' => $expire));
                // 提升效率,暂时不考虑未设置过期时间的问题
/*                if (!($res = $this->driver->set($lockName, $exTimestamp, array('nx', 'ex' => $expire)))) {
                    $ttl = $this->driver->ttl($lockName);
                    if ($ttl == -1) {
                        $res = $this->driver->set($lockName, $exTimestamp, array('ex' => $expire));
                    }
                }*/
            } while (!$res && microtime(true) < $toTimestamp);
        }
        return $res;
    }
    
    /**
     * 测试锁是否存在
     * 
     * @param string $name 锁名
     * @return boolean
     */
    public function isLocking($name)
    {
        $res = false;

        if ($this->isConnected()) {
            $res = $this->connection->exists($this->realKey($name));
        }
        return $res;
    }
    
    /**
     * 解锁
     * 
     * @param string $name 锁名
     * @return boolean
     */
    public function unlock($name)
    {
        $res = false;
        if ($this->isLocking($name)) {
            $res = $this->connection->del($this->realKey($name));
        }
        return $res;
    }
}