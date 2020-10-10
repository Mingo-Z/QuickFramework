<?php
namespace Qf\Components;

use Qf\Kernel\Exception;

class MySQLManagerProvider extends Provider
{
    use ConnectionTrait;

    public $configFile;

    /**
     * @var \mysqli_result
     */
    protected $result;

    protected $lastSql;
    protected $lastExecQueryTime;

    static $queryTotalNum;

    public function __construct()
    {
        $this->isConnected = false;
        $this->lastExecQueryTime = 0;
        $this->connectTimeout = 30;
        self::$queryTotalNum = 0;
    }

    protected function connect()
    {
        if ($this->configFile && is_file($this->configFile)) {
            require $this->configFile;
            $host = isset($host) ? $host : '127.0.0.1';
            $port = isset($port) ? (int)$port : 3306;
            $username = isset($username) ? $username : 'root';
            $password = isset($password) ? $password : '';
            $charset = isset($charset) ? $charset : 'utf8';
            $dbname = isset($dbname) ? $dbname : null;
            $connectTimeout = isset($timeout) ? (int)$timeout : $this->connectTimeout;
            $connection = mysqli_init();
            $connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, $connectTimeout);
            if ($connection->real_connect($host, $username, $password, $dbname, $port)) {
                $this->connection = $connection;
                $this->isConnected = true;
                $this->connection->set_charset($charset);
            } else {
                throw new Exception($this->connection->connect_error, $this->connection->connect_errno);
            }
        }
    }

    public function getError()
    {
        return $this->connection->connect_error ?: $this->connection->error;
    }

    public function getErrno()
    {
        return $this->connection->connect_errno ?: $this->connection->errno;
    }

    public function selectDb($dbName)
    {
        $ret = false;

        if ($this->isConnected()) {
            $ret = $this->connection->select_db($dbName);
        }

        return $ret;
    }

    public function query($sql)
    {
        if ($this->isConnected()) {
            $startQueryTs = microtime(true);
            $this->lastSql = $sql;
            $this->result = $this->connection->query($sql);
            self::$queryTotalNum++;
            if (!$this->result) {
                throw new Exception($this->connection->error . ", SQL: $sql", $this->connection->errno);
            }
            $this->lastExecQueryTime = microtime(true) - $startQueryTs;
            if (isDebug() && ($this->com->sqllog)) {
                $this->com->sqllog->debug("SQL: $sql, Execution time: {$this->lastExecQueryTime}sec");
            }
        }

        return $this->result;
    }

    public function getLastAutoInsertId()
    {
        return $this->connection->insert_id;
    }

    public function update($table, array $fields, $condition)
    {
        $ret = false;
        if ($condition) {
            $sql = "UPDATE $table SET ";
            foreach ($fields as $fieldName => $fieldValue) {
                $sql .= "`$fieldName` = '$fieldValue',";
            }
            $sql = rtrim($sql, ',') . ' WHERE ' . $condition;
            $ret = $this->query($sql);
        }

        return $ret;
    }

    public function insert($table, array $fields)
    {
        $sql = "INSERT INTO $table SET ";
        foreach ($fields as $fieldName => $fieldValue) {
            $sql .= "`$fieldName` = '$fieldValue',";
        }
        $sql = rtrim($sql, ',');

        return $this->query($sql);
    }

    protected function driverPing()
    {
        $ret = false;
        if ($this->isConnected(false)) {
            $ret = $this->connection->ping();
        }

        return $ret;
    }

    public function delete($table, $condition)
    {
        $ret = false;
        if ($condition) {
            $sql = "DELETE FROM $table WHERE $condition";
            $ret = $this->query($sql);
        }

        return $ret;
    }

    public function fetchAssoc($sql)
    {
        $ret = null;

        if ($this->query($sql)) {
            $ret = $this->result->fetch_assoc();
        }

        return $ret;
    }
    public function escape($string)
    {
        return $this->isConnected(false) ? $this->connection->escape_string($string) : addslashes($string);
    }

    public function freeResult()
    {
        if ($this->result && is_object($this->result)) {
            $this->result->free_result();
        }
    }

    public function fetchAllAssoc($sql)
    {
        $ret = null;
        if ($this->query($sql)) {
            $ret = $this->result->fetch_all(MYSQLI_ASSOC);
        }

        return $ret;
    }

    protected function driverDisConnect()
    {
        if ($this->isConnected(false)) {
            $this->connection->close();
            $this->freeResult();
            $this->result = null;
        }
    }

    public function setAutoCommit($mode)
    {
        $isOk = false;
        if ($this->isConnected(true)) {
            $isOk = $this->connection->autocommit($mode);
        }

        return $isOk;
    }

    public function beginTransaction()
    {
        $isOk = false;
        if ($this->isConnected(true)) {
            $isOk = $this->connection->begin_transaction();
        }

        return $isOk;
    }

    public function commit()
    {
        $isOk = false;
        if ($this->isConnected(true)) {
            $isOk = $this->connection->commit();
        }

        return $isOk;
    }

    public function rollback()
    {
        $isOk = false;
        if ($this->isConnected(true)) {
            $isOk = $this->connection->rollback();
        }

        return $isOk;
    }
}