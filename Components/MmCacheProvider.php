<?php
namespace Qf\Components;

use Qf\Kernel\Exception;

/**
 *
 * 基于memcached的内存缓存实现
 *
 * @version $Id: $
 */
class MmCacheProvider extends Provider
{
    use ConnectionTrait;

    public $configFile;
    public $servers;
    public $expire;
    public $autoCompressThreshold;
    public $compressed;
    public $isPersistent;
    public $prefix;
    
    public function __construct()
    {
        $this->isConnected = false;
        $this->isPersistent = false;
        $this->connectTimeout = 30;
        $this->prefix = 'mmCache_';
        $this->autoCompressThreshold = 0;
        $this->expire = 3600;
    }

    protected function driverPing()
    {
        return $this->set('ping', time());
    }

    protected function connect()
    {
        if (!$this->configFile || !is_file($this->configFile)) {
            throw new Exception('MmCache configFile not specified or not a file');
        }
        require $this->configFile;
        if (isset($servers) && is_array($servers)) {
            $persistentId = '';
            if ($this->isPersistent) {
                $persistentId = md5($this->encode($servers));
            }
            $this->connection = new \Memcached($persistentId);
            $this->connection->setOption(\Memcached::OPT_CONNECT_TIMEOUT, $this->connectTimeout);
            if ($this->compressed) {
                $this->connection->setOption(\Memcached::OPT_COMPRESSION, true);
            }
            foreach ($servers as $server) {
                $weight = isset($server['weight']) ? $server['weight'] : 1;
                $this->connection->addServer($server['host'], $server['port'], $weight);
            }
            $this->isConnected = true;
        }

    }

    /**
     * 设置缓存
     *
     * @param string $key 缓存键名
     * @param mixed $value 缓存值，支持数组
     * @param int $expire 缓存有效期，服务不重启就一直有效,设置后优先级高于$this->expire
     * @return bool
     */
    public function set($key, $value, $expire = null)
    {
        $ret = false;

        if ($this->isConnected()) {
            $expire = is_null($expire) ? $this->expire : $expire;
            $ret = $this->connection->set($this->realKey($key), $this->encode($value), (int)$expire);
        }

        return $ret;
    }
    
    public function get($key)
    {
        $ret = '';

        if ($this->isConnected()) {
            $ret = $this->decode($this->connection->get($this->realKey($key)));
        }

        return $ret;
    }
    
    public function delete($key)
    {
        $ret = false;

        if ($this->isConnected()) {
            $ret = $this->connection->delete($this->realKey($key));
        }
        return $ret;
    }

    protected function realKey($key)
    {
        return $this->prefix . $key;
    }
    
    protected function driverDisConnect()
    {
        if ($this->isConnected(false) && !$this->isPersistent) {
            $this->connection->close();
        }
    }
}
