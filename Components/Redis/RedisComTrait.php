<?php
namespace Qf\Components\Redis;

use Qf\Kernel\Exception;
use Qf\Components\ConnectionTrait;

trait RedisComTrait {
    use ConnectionTrait;

    public $configFile;
    public $prefix;
    public $isPersistent;


    protected function connect()
    {
        if (!$this->isConnected) {
            if (!$this->configFile || !is_file($this->configFile)) {
                throw new Exception('RedisCache configFile not specified or not a file');
            }
            require $this->configFile;
            $host = isset($host) ? $host : '127.0.0.1';
            $port = isset($port) ? $port : 6379;
            $connectFunc = $this->isPersistent ? 'pconnect' : 'connect';
            $connectTimeout = isset($timeout) ? (int)$timeout : $this->connectTimeout;
            $this->connection = new \Redis();
            if (!$this->connection->$connectFunc($host, $port, $connectTimeout)) {
                throw new Exception("redis $host:$port connection failed");
            } else {
                if (isset($password) && $password && !$this->connection->auth($password)) {
                    throw  new Exception("redis $host:$port auth failed");
                }
                $this->isConnected = true;
            }
        }
    }

    protected function realKey($custName = null)
    {
        $realKey = $this->prefix;
        if (property_exists($this, 'name')) {
            $realKey .= $this->name;
        }
        if ($custName !== null) {
            $realKey .= $custName;
        }

        if (!$realKey) {
            throw new Exception(__METHOD__ . 'parameter is empty and the name attribute is not specified');
        }

        return $realKey;
    }

    protected function driverDisConnect()
    {
        if ($this->isConnected(false)) {
            $this->connection->close();
        }
    }

    protected function driverPing()
    {
        $ret = false;
        if ($this->isConnected(false)) {
            $ret = $this->connection->ping();
            $this->checkError();
        }

        return $ret;
    }

    /**
     * 返回最近一次错误信息并且清除
     *
     * @return string|null
     */
    public function getError()
    {
        $error = null;
        if ($this->connection) {
            $error = $this->connection->getLastError();
            $this->connection->clearLastError();
        }

        return $error;
    }

    /**
     * 检查抛出错误
     */
    protected function checkError()
    {
        if (($error = $this->getError())) {
            throw new Exception($error);
        }
    }

    /**
     * 用于动态设置键名
     *
     * @param string $name 键名
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }
}
