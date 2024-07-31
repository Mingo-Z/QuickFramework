<?php
namespace Qf\Components;

use Qf\Kernel\Exception;
use Qf\Kernel\Application;

/**
 *
 *MySQL多数据库操作类，根据指定表的数据库连接配置
 *
 * @version $Id: $
 */

class MysqlDistributedProvider extends Provider
{
    use ConnectionTrait;

    /**
     * 当前数据库连接ID
     *
     * @var string
     */
    protected $connectionId;
    
    /**
     * 已激活的数据库连接
     *
     * @var array
     */
    protected $activedConnections;
    
    /**
     * @var mysqli_result|bool
     */
    protected $cntResult;
    
    /**
     *默认数据库连接配置文件
     *
     * @var string
     */
    public $dbConfigFile;
    /**
     * 针对表的数据库连接配置文件
     *
     * @var string
     */
    public $tablesConfigFile;

    /**
     * 当前对象实例查询次数计数
     *
     * @var int
     */
    protected $queryTimes;

    /**
     * 上次执行sql
     *
     * @var string
     */
    protected $lastSql;

    public function __construct()
    {
        $this->connectionId = 'default';
        $this->activedConnections = [];
        $this->connectTimeout = 30;
        $this->readTimeout = 1800;
        $this->queryTimes = 0;
    }
    
    protected function connect()
    {
        if (!$this->connection) {
            if (!$this->dbConfigFile || !is_readable($this->dbConfigFile)) {
                throw new Exception('MysqlDriver configFile not specified or not a file');
            }
            require $this->dbConfigFile;
            $port = isset($port) ? (int)$port : 3306;
            $charset = isset($charset) ? $charset : 'utf8';
            $connectTimeout = isset($connectTimeout) ? (int)$connectTimeout : $this->connectTimeout;
            $readTimeout = $readTimeout ?? $this->readTimeout;
            $this->connection = mysqli_init();
            $this->connection->options(MYSQLI_OPT_CONNECT_TIMEOUT, $connectTimeout);
            $this->setReadTimeout($this->connection, $readTimeout);
            if ($this->connection->real_connect($host, $username, $password, $dbname, $port)) {
                $this->connection->set_charset($charset);
                $this->activedConnections[$this->connectionId] = $this->connection;
                $this->isConnected = true;
            } else {
                $this->error();
                $this->connection = null;
            }
        }
    }
    
    /**
     *根据表配置数组里的键名，查找并更新对应的数据库连接参数，并且返回真是表名
     *
     * $tablesConfig = array(
     *      // 必须唯一
     *     'demoKey' => array(
     *          'linkId' => 'demo', // 必须唯一
     *          'dbConfigFile' => '',
     *          'tableName' => 'demo_table' // 数据库真是表名
     *     )
     * )
     *
     * 用法：
     * // $object instanceof MysqlDistributedProvider
     *
     * $tableName = $object->getTable('demokey');
     * $sql = "SELECT id FROM $tableName";
     * $object->query($sql);
     *
     * @param  string $tableKey 表配置数组的键名，可以与表名相同
     * @return string
     */
    public function getTable($tableKey)
    {
        static $tablesConfig = array();

        if (!$tablesConfig && $this->tablesConfigFile && is_readable($this->tablesConfigFile)) {
            $tablesConfig = require $this->tablesConfigFile;
        }
        $tableName = $tableKey;
        $connectionId = 'default';
        $dbConfigFile = AppConfigsPath . 'servers/mysql/default.config.php';
        if (isset($tablesConfig[$tableKey])) {
            $tableConfig = $tablesConfig[$tableKey];
            $tableName = $tableConfig['tableName'] ?? $tableName;
            $connectionId = $tableConfig['connectionId'] ?? $connectionId;
            $dbConfigFile = $tableConfig['dbConfigFile'];
        }

        $this->freeCntResult();
        if ($this->connectionId != $connectionId) {
            $this->connectionId = $connectionId;
            $this->connection = null;
            if (isset($this->activedConnections[$this->connectionId])) {
                $this->connection = $this->activedConnections[$this->connectionId];
            } else {
                $this->dbConfigFile = $dbConfigFile;
                $this->isConnected = false;
            }
        }

        return $tableName;
    }

    /**
     * 针对当前查询，统计实际返回的记录数量，
     * 不能用于COUNT SQL查询
     *
     * @return int
     */
    public function getRowCount()
    {
        $ret = 0;
        if (is_object($this->cntResult)) {
            $ret = $this->cntResult->num_rows;
        }
        return $ret;
    }

    /**
     *转换适配MysqliManager数据库操作对象
     *
     * @param MysqliManager $dbManager
     * @param string $linkId
     * @return bool
     */
    public function setDbCntLinkOfDbManager(MysqliManager $dbManager, $linkId = 'sugarDb')
    {
        $ret = false;
        if ($dbManager->database && mysqli_ping($dbManager->database)) {
            $this->connection = $dbManager->database;
            $this->connectionId = $linkId;
            $this->activedConnections[$linkId] = $this->connection;
            $ret = true;
        }
        return $ret;
    }

    protected function driverDisConnect()
    {
        if ($this->connection) {
            $this->connection->close();
            $this->activedConnections[$this->connectionId] = null;
        }
    }

    protected function driverPing()
    {
        return $this->connection->ping();
    }

    public function getAffectedRows()
    {
        return $this->connection ? $this->connection->affected_rows : 0;
    }
    
    public function getLastAutoInsertId()
    {
        return $this->connection ? $this->connection->insert_id : 0;
    }
    
    public function escape($string)
    {
        return $this->connection ? $this->connection->escape_string($string) : addslashes($string);
    }

    public function update($table, array $fields, $whereStr)
    {
        $ret = false;
        if ($whereStr) {
            $sql = "UPDATE $table SET ";
            foreach ($fields as $fieldName => $fieldValue) {
                $sql .= "`$fieldName` = '$fieldValue',";
            }
            $sql = rtrim($sql, ',') . ' WHERE ' . $whereStr;
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

    public function delete($table, $whereStr)
    {
        $ret = false;
        if ($whereStr) {
            $sql = "DELETE FROM $table WHERE $whereStr";
            $ret = $this->query($sql);
        }

        return $ret;
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

    /**
     * 执行SQL查询，如果有WHERE条件，建议在传入之间通过
     * 调用include/utils/function.php文件中的
     * parseConds组装
     *
     * @param string $sql
     * @param string $tableKey 表配置数组键名
     * @param bool $returnResultObject 是否返回\mysqli_result对象
     * @return bool|\mysqli_result
     */
    public function query($sql, $tableKey = '', $returnResultObject = false)
    {
        if ($tableKey) {
            $tableName = $this->getTable($tableKey);
            // 填充真实表名, eg: $sql = 'SELECT id FROM %s'
            $sql = sprintf($sql, $tableName);
        }
        if ($this->isConnected()) {
            $this->freeCntResult();
            $startQueryTime = microtime(true);
            $this->lastSql = $sql;
            $this->cntResult = $this->connection->query($sql);
            $endQueryTime = microtime(true);
            Application::getCom()->sqllog->debug('SQL: ' . $sql . ', Execution Time: ' . round($endQueryTime - $startQueryTime, 3) . 'sec');
            $this->queryTimes++;
            if (!$this->cntResult) {
                $this->error();
            }
        }

        return $this->cntResult ? ($returnResultObject ? $this->cntResult : true) : false;
    }

    /**
     * 直接调用\mysqli_result::fetch_assoc
     *
     * @return false
     */
    public function rawFetchAssoc()
    {
        $ret = false;

        if ($this->cntResult) {
            $ret = $this->cntResult->fetch_assoc();
        }

        return $ret;
    }

    /**
     * 获取当前对象实例的查询次数
     *
     * @return int
     */
    public function getQueryTimes()
    {
        return $this->queryTimes;
    }

    /**
     * 基于当前数据库链接上切换数据库
     *
     * @param string $dbname
     * @return bool
     */
    public function selectDb($dbname)
    {
        $ret = false;

        if ($this->connection) {
            $ret = $this->connection->select_db($dbname);
            if (!$ret) {
                $this->error();
            }
        }
        return $ret;
    }
    
    /**
     * 关闭所有已激活的数据库连接
     */
    protected function closeActivedLinks()
    {
        if ($this->activedConnections) {
            foreach ($this->activedConnections as $connectionId => $connection) {
                $connection->close();
                $this->activedConnections[$connectionId] = null;
            }
        }
        $this->connection = null;
        $this->connectionId = '';

    }
    
    protected function freeCntResult()
    {
        if (is_object($this->cntResult)) {
            $this->cntResult->free();
            $this->cntResult = null;
        }
    }

    /**
     *获取单行记录，$sql参数为空时，直接调用上次查询
     * 结果对象（兼容原有数据库实现调用方式，先query再fetch）
     *
     * @param string $sql
     * @param string $tableKey
     * @return array
     */
    public function fetchAssoc($sql = '', $tableKey = '')
    {
        $ret = array();
        if ($sql) {
            $this->query($sql, $tableKey);
        }
        if (is_object($this->cntResult)) {
            $ret = $this->cntResult->fetch_assoc();
        }
        return $ret;
    }

    /**
     *获取所有结果集，$sql参数为空时，直接调用上次查询
     * 结果对象（兼容原有数据库实现调用方式，先query再fetch）
     *
     * @param string $sql
     * @param string $tableKey
     * @return array
     */
    public function fetchAllAssoc($sql = '', $tableKey = '')
    {
        $ret = array();
        if ($sql) {
            $this->query($sql, $tableKey);
        }
        if (is_object($this->cntResult)) {
            $ret = $this->cntResult->fetch_all(MYSQLI_ASSOC);
            $this->freeCntResult();
        }
        return $ret;
    }
    
    public function __destruct()
    {
        $this->freeCntResult();
        $this->closeActivedLinks();
    }

    /**
     *判断上一步调用是否发生错误
     *
     * @return bool
     */
    public function isError()
    {
        $ret = false;
        if ($this->connection) {
            $ret = (bool)$this->connection->errno;
        }
        return $ret;
    }

    protected function error()
    {
        $error = 'Unknown error';
        $errno = -1;
        if ($this->connection) {
            $error = $this->connection->error;
            $errno = $this->connection->errno;
        }
        // connection has gone away
        if ($errno === 2006) {
            $this->disConnect();
        }
        trigger_error($error . "(SQL errno: $errno), SQL: {$this->lastSql}", E_USER_WARNING);
    }

    protected function setReadTimeout(\mysqli $connection, $seconds)
    {
        $seconds = (int)$seconds;
        if (!defined('MYSQLI_OPT_READ_TIMEOUT')) {
            define('MYSQLI_OPT_READ_TIMEOUT', 11);
        }
        return $connection->options(MYSQLI_OPT_READ_TIMEOUT, $seconds);
    }
}

