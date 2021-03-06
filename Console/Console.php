<?php
namespace Qf\Console;

use Qf\Kernel\Application;
use Qf\Kernel\Exception;
use Qf\Kernel\Http\Request;

require_once __DIR__ . '/../Kernel/Application.php';

class Console extends Application
{
    protected $runCmdArgs;

    protected function __construct($appPath)
    {
        parent::__construct($appPath);
        $this->request = (new Request())->init();
        if (!$this->request->isCli()) {
            throw new Exception('Console can only be run in command line mode');
        }
        $this->parseRunCmdArgs();
    }

    public function execute()
    {
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
        $result = $controllerInstance->$action($this->runCmdArgs);
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
        return ini_set('memory_limit', $bytes);
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

    protected function parseRunCmdArgs()
    {
        // -c controller name required
        $argv = $this->request->getArgv();
        if (count($argv) >= 2) {
            array_shift($argv);
            $index = 0;
            $argCount = count($argv);
            while ($index < $argCount) {
                $cntArg = $argv[$index];
                $nextArg = isset($argv[$index + 1]) ? $argv[$index + 1] : null;
                switch ($cntArg) {
                    case '-m':
                    case '-c':
                    case '-a':
                        if ($nextArg && $nextArg[0] != '-') {
                            if ($cntArg == '-m') {
                                $this->runCmdArgs['module'] = $nextArg;
                            } elseif ($cntArg == '-c') {
                                $this->runCmdArgs['controller'] = $nextArg;
                            } else {
                                $this->runCmdArgs['action'] = $nextArg;
                            }
                            $index++;
                        }
                        break;
                    default:
                        if ($cntArg[0] == '-') {
                            $key = ltrim($cntArg, '-');
                            if ($nextArg && $nextArg[0] != '-') {
                                $this->runCmdArgs[$key] = $nextArg;
                                $index++;
                            } else {
                                $this->runCmdArgs[$key] = '';
                            }
                        } else {
                            $this->runCmdArgs[] = $cntArg;
                        }

                }
                $index++;
            }
        }
    }

    public function getControllerName($default = null)
    {
        return isset($this->runCmdArgs['controller']) ? $this->runCmdArgs['controller'] : $default;
    }

    public function getActionName($default = null)
    {
        return isset($this->runCmdArgs['action']) ? $this->runCmdArgs['action'] : $default;
    }

    public function getModuleName($default = null)
    {
        return isset($this->runCmdArgs['module']) ? $this->runCmdArgs['module'] : $default;
    }

}