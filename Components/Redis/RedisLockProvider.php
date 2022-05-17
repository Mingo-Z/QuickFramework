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
     * @param string|int 锁持有者
     * @return boolean
     */
    public function lock($name, $wait = 3, $expire = 10, $owner = null)
    {
        $res = false;

        if ($this->isConnected()) {
            $toTimestamp = time() + $wait;
            do {
                $exTimestamp = time() + $expire;
                $lockName = $this->realKey($name);
                $res = $this->connection->set($lockName, "$exTimestamp|$owner", array('nx', 'ex' => $expire));
                $this->checkError();
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
            $this->checkError();
        }
        return $res;
    }
    
    /**
     * 解锁
     * 
     * @param string $name 锁名
     * @param  string|int 锁的持有者，如果设置该参数则会检查锁是否属于该用户，属于才能解锁
     * @return boolean
     */
    public function unlock($name, $owner = null)
    {
        $res = false;
        if ($this->isLocking($name)) {
            $realName = $this->realKey($name);
            if ($owner) {
                $lockInfo = explode('|', $this->connection->get($realName));
                if (isset($lockInfo[1]) && $lockInfo[1] == $owner) {
                    $this->connection->del($realName);
                }
            } else {
                $res = $this->connection->del($realName);
            }
            $this->checkError();
        }
        return (bool)$res;
    }
}

