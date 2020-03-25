<?php
namespace Qf\Components;

trait ConnectionTrait
{
    protected $isConnected;
    protected $connection;
    /**
     * 连接超时时间，单位：s
     *
     * @var int
     */
    public $connectTimeout;

    abstract protected function connect();

    abstract protected function driverDisConnect();

    abstract protected function driverPing();

    protected function isConnected($isAutoConnect = true)
    {
        if (!$this->isConnected && $isAutoConnect) {
            $this->connect();
        }

        return $this->isConnected;
    }
    protected function disConnect()
    {
        if ($this->isConnected(false)) {
            $this->driverDisconnect();
            $this->connection = null;
            $this->isConnected = false;
        }
    }

    public function __destruct()
    {
        $this->disConnect();
    }

    public function ping($isAutoConnect = false)
    {
        $ret = false;

        if ($this->isConnected(false)) {
            $ret = $this->driverPing();
            if (!$ret && $isAutoConnect) {
                $this->disConnect();
            }
        }

        return $ret;
    }
}