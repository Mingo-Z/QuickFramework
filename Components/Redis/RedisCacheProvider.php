<?php
namespace Qf\Components\Redis;

use Qf\Components\Provider;

/**
 *
 * 基于redis的缓存实现，可用于持久储存
 *
 * @version $Id: $
 */
class RedisCacheProvider extends Provider
{
    use RedisComTrait;
    
    public function __construct()
    {
        $this->isConnected = false;
        $this->isPersistent = false;
        $this->connectTimeout = 30;
    }
    
    /**
     * @desc 设置hash表指定key的一个field
     * @param string $key 缓存键名
     * @param string $field filed
     * @param mixed $value 缓存值，支持数组
     * @return bool
     */
    public function setHashTable($key, $field, $value)
    {
    	$ret = false;

    	if ($this->isConnected()) {
    		$ret = $this->connection->hSet($this->realKey($key), $field, $this->encode($value) );
    		$this->checkError();
    	}

    	return $ret;
    }

    public function subscribe(array $channels, callable $callback)
    {
        $ret = false;

        if ($this->isConnected()) {
            $channels = array_map(function ($elem) {
                return $this->realKey($elem);
            }, $channels);
            $ret = $this->connection->subscribe($channels, $callback);
            $this->checkError();
        }

        return $ret;
    }

    public function unsubscribe($channel)
    {
        $ret = false;

        if ($this->isConnected()) {
            $ret = $this->connection->unsubscribe($this->realKey($channel));
            $this->checkError();
        }

        return $ret;
    }

    public function publish($channel, $message)
    {
        $ret = false;

        if ($this->isConnected()) {
            $ret = $this->connection->publish($this->realKey($channel), $this->encode($message));
            $this->checkError();
        }

        return $ret;
    }

    /**
     * 
     *@desc 得到hash表指定key的一个field的值
     * @param string $key 缓存键名
     * @param string $field  field
     * @return mixed
     */
    public function getHashTable($key, $field)
    {
    	$ret = '';

    	if ($this->isConnected()) {
    		$ret = $this->decode($this->connection->hGet($this->realKey($key), $field));
            $this->checkError();
        }

    	return $ret;
    }

    /**
     *
     *@desc 删除hash表指定key的一个field
     * @param string $key 缓存键名
     * @param string $field  field
     * @return boolean
     */
    public function delHashTable($key, $field)
    {
    	$ret = false;

    	if ($this->isConnected()) {
    		$ret = $this->connection->hDel($this->realKey($key), $field);
            $this->checkError();
        }

    	return $ret;
    }
    
    /**
     * 设置缓存
     * 
     * @param string $key 缓存键名
     * @param mixed $value 缓存值，支持数组
     * @param int $expire 缓存有效期，null为永久储存,设置后优先级高于$this->expire
     * @return bool
     */
    public function set($key, $value, $expire = null)
    {
        $ret = false;

        if ($this->isConnected()) {
            $expire = is_null($expire) ? $expire : (int)$expire;
            $ret = $this->connection->set($this->realKey($key), $this->encode($value), $expire);
            $this->checkError();
        }

        return $ret;
    }
    
    /**
     * 获取缓存
     * 
     * @param string $key 缓存键名
     * @return mixed
     */
    public function get($key)
    {
        $ret = '';

        if ($this->isConnected()) {
            $response = $this->connection->get($this->realKey($key));
            $this->checkError();
            if ($response) {
                $ret = $this->decode($response);
            }
        }

        return $ret;
    }
    
    /**
     * 删除缓存
     * 
     * @param string $key 缓存键名
     * @return bool
     */
    public function delete($key)
    {
        $ret = false;

        if ($this->isConnected()) {
            $ret = $this->connection->delete($this->realKey($key));
            $this->checkError();
        }

        return $ret;
    }
    
    /**
     * 批量设置缓存
     * @todo 暂时只能设置永久缓存
     * 
     * @param array $kvs 键值数组
     * @return bool
     */
    public function setArray(array $array)
    {
        $ret = false;

        if ($array) {
            $realArray = [];
            if ($this->isConnected()) {
                foreach ($array as $key => $value) {
                    $realArray[$this->realKey($key)] = $this->encode($value);
                }
                $ret = $this->connection->mset($realArray);
                $this->checkError();
            }
        }

        return $ret;
    }
    
    /**
     * 批量获取缓存
     * 
     * @param array $keys
     * @return array
     */
    public function getArray(array $keys)
    {
        $realKeys = $values = array();

        if ($keys) {
            if ($this->isConnected()) {
                foreach ($keys as $key) {
                    $realKeys[] = $this->realKey($key);
                }
                $values = $this->connection->mget($realKeys);
                $this->checkError();
                if ($values) {
                    foreach ($values as $index => $val) {
                        $values[$index] = $this->decode($val);
                    }
                }
            }
        }

        return $values;
    }

    /**
     * redis环境执行lua脚本
     *
     * @param string $code lua code
     * @param int $keysNum key参数的数量
     * @param mixed ...$arguments
     * @return mixed
     */
    public function evalLuaCode($code, $keysNum, ...$arguments)
    {
        $retValue = null;
        if ($this->isConnected()) {
            $retValue = $this->connection->eval($code, $arguments, $keysNum);
            $this->checkError();
        }

        return $retValue;
    }
}