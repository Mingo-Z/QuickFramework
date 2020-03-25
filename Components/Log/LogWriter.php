<?php
namespace Qf\Components\Log;

abstract class LogWriter {

    protected $options = [];

    public function setOptions(array $options)
    {
        $this->options = array_merge($this->options, $options);
    }

    abstract function write($logMsg);
}