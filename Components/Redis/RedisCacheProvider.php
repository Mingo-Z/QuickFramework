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

    /**
     * 获取全表记录
     *
     * @param string $key
     * @return array
     * @throws \Qf\Kernel\Exception
     */
    public function getAllHashTable($key)
    {
        $list = [];

        if ($this->isConnected()) {
            $resp = $this->connection->hGetAll($this->realKey($key));
            $this->checkError();
            if (is_array($resp)) {
                foreach ($resp as $key => $value) {
                    $list[$key] = $this->decode($value);
                }
            }
        }

        return $list;
    }

    /**
     * 获取表记录数量
     *
     * @param string$key
     * @return int
     * @throws \Qf\Kernel\Exception
     */
    public function lenHashTable($key)
    {
        $len = 0;

        if ($this->isConnected()) {
            $len = $this->connection->hLen($this->realKey($key));
            $this->checkError();
        }

        return $len;
    }

    /**
     * 判断字段在表里是否存在
     *
     * @param string $key
     * @param string $field
     * @return bool
     * @throws \Qf\Kernel\Exception
     */
    public function existsHashTable($key, $field)
    {
        $ret = false;

        if ($this->isConnected()) {
            $ret = $this->connection->hExists($this->realKey($key), $field);
            $this->checkError();
        }

        return $ret;
    }

    /**
     * 订阅
     *
     * @param array $channels
     * @param callable $callback the callback function receives 3 parameters: the redis instance,
     * the channel name, and the message. return value: mixed. any non-null return value in the callback will be returned to the caller.
     * @return mixed
     * @throws \Qf\Kernel\Exception
     */
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
        return $this->deleteKey($key);
    }

    /**
     * 检查key是否存在
     *
     * @param string $key
     * @return bool
     * @throws \Qf\Kernel\Exception
     */
    public function exists($key)
    {
        return $this->existsKey($key);
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

    /**
     * 设置指定键名的位值
     *
     * @param string $key
     * @param int $offset
     * @param int $value is 0 or 1
     * @return int 原来的位值
     * @throws \Qf\Kernel\Exception
     */
    public function setBit($key, $offset, $value)
    {
        $retValue = 0;
        if ($this->isConnected()) {
            $retValue = $this->connection->setbit($this->realKey($key), (int)$offset, (int)$value);
            $this->checkError();;
        }

        return $retValue;
    }

    /**
     * 获取指定键名的位值
     *
     * @param string $key
     * @param int $offset
     * @return int is 0 or 1
     * @throws \Qf\Kernel\Exception
     */
    public function getBit($key, $offset)
    {
        $retValue = 0;
        if ($this->isConnected()) {
            $retValue = $this->connection->getbit($this->realKey($key), (int)$offset);
            $this->checkError();;
        }

        return $retValue;
    }

    /**
     * 获取指定键名位值等于1的数量
     *
     * @param string $key
     * @return int
     * @throws \Qf\Kernel\Exception
     */
    public function bitCount($key)
    {
        $retValue = 0;
        if ($this->isConnected()) {
            $retValue = $this->connection->bitcount($this->realKey($key));
            $this->checkError();;
        }

        return $retValue;
    }
}
