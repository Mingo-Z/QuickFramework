<?php
namespace Qf\Components\Log;

use Qf\Kernel\Exception;

class LogManager
{
    public $runLogLevel;
    public $driverType;
    public $options;

    const LOG_LEVEL_DEBUG = 1;
    const LOG_LEVEL_NOTICE = 2;
    const LOG_LEVEL_WARNING = 4;
    const LOG_LEVEL_ERROR = 8;
    const LOG_LEVEL_FATAL = 16;

    protected static $levelMapMethods = [
        'debug' => self::LOG_LEVEL_DEBUG,
        'notice' => self::LOG_LEVEL_NOTICE,
        'warn' => self::LOG_LEVEL_WARNING,
        'error' => self::LOG_LEVEL_ERROR,
        'fatal' => self::LOG_LEVEL_FATAL,
    ];

    protected static $drivers = [
        'file' => LogFileWriter::class,
        'output' => LogOutputWriter::class,
    ];

    /**
     * @var LogWriter;
     */
    protected $logger;

    public function __construct()
    {
        $this->runLogLevel = self::LOG_LEVEL_WARNING;
        $this->driverType = 'file';
    }

    public function init()
    {
        if (!isset(self::$drivers[$this->driverType])) {
            throw new Exception("Log driver {$this->driverType} does not exist");
        }
        $loggerClass = self::$drivers[$this->driverType];
        $this->logger = new $loggerClass;
        $this->logger->setOptions($this->options ?? []);
    }

    public function __call($method, array $arguments)
    {
        $writeLen = 0;

        if (isset(self::$levelMapMethods[$method])) {
            $level = self::$levelMapMethods[$method];
            if ($level >= $this->runLogLevel) {
                $message = $arguments[0] ?? '';
                $prefix = $arguments[1] ?? '';
                $writeLen = $this->log($method, $message, $prefix);
            }
        }

        return $writeLen;
    }

    protected function log($levelStr, $message, $prefix = null)
    {
        if (!is_scalar($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }
        $logMsg = sprintf("[%s][%s][%s][%d][%s] \t%s\n", date('r'),
            defined('AppName') ? AppName : 'unknown', $levelStr, posix_getpid(), $prefix, $message);
        return $this->logger->write($logMsg);
    }
}
