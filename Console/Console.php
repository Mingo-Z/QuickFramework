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

        $module = $this->getModuleName(envIniConfig('defaultModuleName', 'console'));
        $controller = $this->getControllerName(envIniConfig('defaultControllerName', 'console', 'Index'));
        $action = $this->getActionName(envIniConfig('defaultActionName', 'console', 'Index'));
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

    public static function runCommand($command, $argsDesc, array $args = null,
                                      $isBackground = false, &$result = null)
    {
        $status = 1;
        $runArgs = $argsDesc;

        if ($args) {
            $args = array_map('escapeshellarg', $args);
            $runArgs = vsprintf($argsDesc, $args);
        }
        $fullCommand = escapeshellcmd($command) . ' ' . $runArgs . ' >> /dev/null 2>&1';
        if ($isBackground) {
            $fullCommand .= ' &';
        }
        exec($fullCommand, $result, $status);

        return !$status;
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
            $nanoseconds = (int)$seconds * 1000000;
            $ret = time_nanosleep((int)($nanoseconds/1000000), $nanoseconds % 1000000);
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

    public function getControllerName($default = null)
    {
        return $this->controllerName ?? $default;
    }

    public function getActionName($default = null)
    {
        return $this->actionName ?? $default;
    }

    public function getModuleName($default = null)
    {
        return $this->moduleName ?? $default;
    }

}