<?php
namespace Qf\Console;

use Qf\Kernel\Application;
use Qf\Kernel\Exception;
use Qf\Kernel\Http\Request;

require_once __DIR__ . '/../Kernel/Application.php';

class Console extends Application
{
    protected $moduleName;
    protected $controllerName;
    protected $actionName;

    protected function __construct($appPath)
    {
        parent::__construct($appPath);
        $this->request = (new Request())->init();
        if (!$this->request->isCli()) {
            throw new Exception('Console can only be run in command line mode');
        }
    }

    public function execute()
    {
        $this->route();

        $module = $this->getModuleName();
        $controller = $this->getControllerName();
        $action = $this->getActionName();
        $class = "Console\\{$controller}Controller";
        if ($module) {
            $class = $module . '\\' . $class;
        }
        $class = 'App\\' . $class;
        if (!class_exists($class, true) || !is_subclass_of($class, Controller::class)) {
            throw new Exception("Console controller $class class does not exists");
        }
        $controllerInstance = new $class($this);
        $result = $controllerInstance->$action();
        if (!is_null($result)) {
            if (!is_scalar($result)) {
                $result = json_encode($result);
            }
            self::response($result);
        }
    }

    /**
     * 安全的执行命令，后台模式运行，可能返回非正确结果
     *
     * @param string $command 可执行命令
     * @param string|array $arguments
     * @param bool $isBackground 是否后台执行，不阻塞当前流程
     * @param array|null $result 执行的输出
     * @return int
     */
    public static function execCommand($command, $arguments = null, $isBackground = false, array &$result = null)
    {
        $statusCode = 1;
        $cmdline = null;

        if ($command) {
            $cmdline .= escapeshellcmd($command);
            if (is_string($arguments)) {
                $cmdline .= ' ' . escapeshellarg($arguments);
            } elseif (is_array($arguments)) {
                foreach ($arguments as $key => $value) {
                    if (is_string($key)) {
                        $cmdline .= ' ' . escapeshellarg($key);
                    }
                    $cmdline .= ' ' . escapeshellarg($value);
                }
            }
            if ($isBackground) {
                $cmdline .= ' >/dev/null &';
            }
            exec($cmdline, $result, $statusCode);
            if ($statusCode !== 0) {
                trigger_error("Failed to execute $cmdline", E_USER_ERROR);
            }
        }

        return $statusCode;
    }

    /**
     * 设置程序允许使用的最大内存
     *
     * @param int $bytes 字节
     * @return bool|string
     */
    public static function setMaxMemory($bytes)
    {
        return ini_set('memory_limit', (int)$bytes);
    }

    /**
     * 进程休眠，支持小数
     *
     * @param double $seconds 休眠时间
     * @return array|bool
     */
    public static function sleep($seconds)
    {
        $ret = false;

        if ($seconds > 0) {
            $nanoseconds = (int)($seconds * 1000000000);
            $ret = time_nanosleep((int)$seconds, $nanoseconds % 1000000000);
        }

        return $ret;
    }

    public static function response($message, $code = 0)
    {
        self::stdout($message);
        exit((int)$code);
    }

    public static function stdout($message)
    {
        return file_put_contents('php://stdout', $message);
    }

    public static function stderr($message)
    {
        return file_put_contents('php://stderr', $message);
    }

    public function route()
    {
        $command = new Command();
        $command->setName('console')
            ->setOption('m', 'module', false, true, false,
            null, null, 'Module name')
            ->setOption('c', 'controller', false, true, false,
                null, null, 'Controller name')
            ->setOption('a', 'action', false, true, false,
                null, null, 'Controller action name');
        $isOk = $command->parse($this->request->getArgv());
        if ($isOk) {
            $this->moduleName = $command->getOptionValue('module', true);
            $this->controllerName = $command->getOptionValue('controller', true);
            $this->actionName = $command->getOptionValue('action', true);
        }
    }

    public function getControllerName()
    {
        return $this->controllerName ?? envIniConfig('defaultControllerName', 'console', 'Index');
    }

    public function getActionName()
    {
        return $this->actionName ?? envIniConfig('defaultActionName', 'console', 'index');
    }

    public function getModuleName()
    {
        return $this->moduleName ?? envIniConfig('defaultModuleName', 'console');
    }

    public static function echoSuccessMessage($message)
    {
        self::stdout(TermColor::addColor($message, TermColor::GREEN));
    }

    public static function echoFailureMessage($message)
    {
        self::stdout(TermColor::addColor($message, TermColor::RED));
    }

}

