<?php
namespace Qf\Kernel\Database;

use Qf\Kernel\Application;
use Qf\Components\MysqlDistributedProvider;
use Qf\Kernel\ComponentManager;

abstract class Model
{
    protected $tableKey;
    /**
     * @var MysqlDistributedProvider
     */
    protected $dbConnection;

    protected $tablePrimaryKey = 'id';

    protected $tableColumnsName = [];

    protected $enableCache = false;

    protected $createdAtFieldName;
    protected $updatedAtFieldName;

    /**
     * @var ComponentManager
     */
    protected $com;

    public function __construct(ComponentManager $com = null)
    {
        if (!$com) {
            $com = Application::getCom();
        }
        $this->com = $com;
        $this->dbConnection = $this->com->database;
        $this->loadTableColumns();
    }

    protected function loadTableColumns($refreshCache = false)
    {
        $cacheTableDdlKey = "model:tableddl:" . $this->tableKey;
        if ($this->enableCache && !$refreshCache) {
            $this->tableColumnsName = $this->getCache($cacheTableDdlKey, []);
        }
        if (!$this->tableColumnsName && $this->tableKey && $this->dbConnection) {
            $tableName = $this->dbConnection->getTable($this->tableKey);
            $sql = "SHOW COLUMNS FROM $tableName";
            foreach ($this->dbConnection->fetchAllAssoc($sql) as $row) {
                $this->tableColumnsName[] = $row['Field'];
                if (!$this->tablePrimaryKey && $row['Key'] == 'PRI') {
                    $this->tablePrimaryKey = $row['Field'];
                }
            }
            if ($this->enableCache && $this->tableColumnsName) {
                $this->setCache($cacheTableDdlKey, $this->tableColumnsName);
            }
        }
        if ($this->tableColumnsName) {
            foreach ($this->tableColumnsName as $columnName) {
                $this->{$columnName} = null;
            }
        }
    }

    protected function setCache($key, $value, $expire = null)
    {
        return $this->com->cache->set($this->realCacheKey($key), $value, $expire);
    }

    protected function getCache($key, $default = null)
    {
        $ret = $this->com->cache->get($this->realCacheKey($key));

        return $ret ?: $default;
    }

    protected function delCache($key)
    {
        return $this->com->cache->delete($this->realCacheKey($key));
    }

    protected function realCacheKey($key)
    {
        return "table:{$this->tableKey}:row:" . md5(json_encode($key));
    }

    public function update(array $columns, array $rule)
    {
        $ret = false;

        $condition = self::parseCondRule($rule);
        if (isset($condition['where'])) {
            if ($this->updatedAtFieldName && in_array($this->updatedAtFieldName, $this->tableColumnsName)) {
                $columns[$this->updatedAtFieldName] = time();
            }
            $tableName = $this->dbConnection->getTable($this->tableKey);
            $ret = $this->dbConnection->update($tableName, $columns, $condition['where']);
        }

        return $ret;
    }

    public function insert(array $columns)
    {
        $ret = false;
        if ($this->createdAtFieldName && in_array($this->createdAtFieldName, $this->tableColumnsName)) {
            $columns[$this->createdAtFieldName] = time();
        }
        if ($this->updatedAtFieldName && in_array($this->updatedAtFieldName, $this->tableColumnsName)) {
            $columns[$this->updatedAtFieldName] = time();
        }
        $tableName = $this->dbConnection->getTable($this->tableKey);
        if ($this->dbConnection->insert($tableName, $columns)) {
            $ret = $this->dbConnection->getLastAutoInsertId();
        }

        return $ret;
    }

    public function delete(array $rule)
    {
        $ret = false;

        $condition = self::parseCondRule($rule);
        if (isset($condition['where'])) {
            $tableName = $this->dbConnection->getTable($this->tableKey);
            $ret = $this->dbConnection->delete($tableName, $condition['where']);
        }

        return $ret;
    }

    protected static function parseCondRule(array $rule)
    {
        return parseConds($rule);
    }

    public function populateRowToTableColumns(array $row)
    {
        foreach ($this->tableColumnsName as $columnName) {
            if (isset($row[$columnName])) {
                $this->$columnName = $row[$columnName];
            }
        }

        return $this;
    }

    public function get($id)
    {
        $rule = [
            'query' => "{$this->tablePrimaryKey} = '$id'",
        ];
        $row = [];
        if (!$this->enableCache || ($this->enableCache && !$row = $this->getCache($rule))) {
            $row = $this->fetchAssoc($rule);
            if ($row && $this->enableCache) {
                $this->setCache($rule, $row);
            }
        }
        if ($row) {
            $this->populateRowToTableColumns($row);
        }

        return $this->{$this->tablePrimaryKey};
    }

    public function toTableColumnsArray()
    {
        $columns = [];
        foreach ($this->tableColumnsName as $columnName) {
            if (!is_null($this->$columnName)) {
                $columns[$columnName] = $this->$columnName;
            }
        }

        return $columns;
    }

    public function save()
    {
        $ret = false;

        $tablePrimaryKey = $this->tablePrimaryKey;
        $isNewCreate = !$this->$tablePrimaryKey;
        $columns = $this->toTableColumnsArray();
        if ($isNewCreate) {
            if (($ret = $this->insert($columns))) {
                $this->$tablePrimaryKey = $ret;
            }
        } elseif ($this->$tablePrimaryKey) {
            $rule = [
                'query' => "$tablePrimaryKey = '{$this->$tablePrimaryKey}'",
            ];
            if (($ret = $this->update($columns, $rule)) && $this->enableCache) {
                $this->delCache($rule);
            }
        }

        return $ret;
    }

    public function fetchAssoc(array $rule, array $columns = ['*'])
    {
        $ret = false;

        $condition = self::parseCondRule($rule);
        if (isset($condition['where'])) {
            $tableName = $this->dbConnection->getTable($this->tableKey);
            $sql = 'SELECT ' . join(', ', $columns) . ' FROM ' . $tableName . ' WHERE ' . $condition['where'];
            if (isset($condition['order'])) {
                $sql .= $condition['order'];
            }
            $sql .= ' LIMIT 1';
            $ret = $this->dbConnection->fetchAssoc($sql);
        }

        return $ret;
    }

    public function rawQuery($sql, $tableKey = '')
    {
        return $this->dbConnection->query($sql, $tableKey);
    }

    public function fetchAllAssoc(array $rule, array $columns = ['*'])
    {
        $ret = false;

        $condition = self::parseCondRule($rule);
        if (isset($condition['where'])) {
            $tableName = $this->dbConnection->getTable($this->tableKey);
            $sql = 'SELECT ' . join(', ', $columns) . ' FROM ' . $tableName . ' WHERE ' . $condition['where'];
            if (isset($condition['order'])) {
                $sql .= ' ' .$condition['order'];
            }
            if (isset($condition['limit'])) {
                $sql .= ' ' . $condition['limit'];
            }
            $ret = $this->dbConnection->fetchAllAssoc($sql);
        }

        return $ret;
    }

    public function getTableKey()
    {
        return $this->tableKey;
    }
}