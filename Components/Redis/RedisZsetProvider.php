<?php
namespace Qf\Components\Redis;

use Qf\Components\Provider;

class RedisZsetProvider extends Provider
{
    use RedisComTrait;

    public $name;

    public function __construct()
    {
        $this->connectTimeout = 30;
    }

    public function add($elem, $score = 0)
    {
        $ret = false;

        if ($this->isConnected()) {
            $ret = $this->connection->zAdd($this->realKey(), $score, $this->encode($elem));
            $this->checkError();
        }

        return $ret;
    }

    public function count($min = null, $max = null)
    {
        $ret = 0;
        $min = $min ?: '-inf';
        $max = $max ?: '+inf';
        if (((is_numeric($max) || $max == '+inf') && (is_numeric($min) || $min == '-inf'))
            && $this->isConnected()) {
            $ret = $this->connection->zCount($this->realKey(), $min, $max);
            $this->checkError();
        }

        return $ret;
    }

    public function del($elem)
    {
        $ret = false;
        if ($this->isConnected()) {
            $ret = $this->connection->zDelete($this->realKey(), $this->encode($elem));
            $this->checkError();
        }

        return $ret;
    }

    public function delElems($min, $max)
    {
        $ret = false;
        if (((is_numeric($max) || $max == '+inf') && (is_numeric($min) || $min == '-inf'))
            && $this->isConnected()) {
            $ret = $this->connection->zDeleteRangeByScore($this->realKey(), $min, $max);
            $this->checkError();
        }

        return $ret;
    }

    public function listElems($min = null, $max = null, $offset = null, $limit = null, $isPop = false)
    {
        $elems = [];

        $min = $min ?: '-inf';
        $max = $max ?: '+inf';
        if (((is_numeric($max) || $max == '+inf') && (is_numeric($min) || $min == '-inf'))
            && $this->isConnected()) {
            $options = [];
            if ($offset >= 0 && $limit > 0) {
                $options['limit'] = [(int)$offset, (int)$limit];
            }
            $values = $this->connection->zRangeByScore($this->realKey(), $min, $max, $options);
            $this->checkError();
            if ($values) {
                foreach ($values as $index => $value) {
                    $elems[$index] = $this->decode($value);
                    if ($isPop) {
                        $this->del($elems[$index]);
                    }
                }
            }
        }

        return $elems;
    }
}
