<?php
namespace Qf\Components;

use Qf\Console\Console;

class ProcessManagerProvider extends Provider
{
    protected static $workers = [];
    protected static $workersPid = [];
    protected static $logFilePath = '/dev/null';

    public static function addWorker($name, callable $callback, array $arguments = [])
    {
        self::$workers[$name] = [
            'name' => $name,
            'callback' => $callback,
            'arguments' => $arguments,
        ];
    }

    /**
     * 添加作业进程
     *
     * @param string $name 作业名称
     * @param callable $callback 处理器
     * @param int $num 进程数量
     * @param array $arguments 作业参数
     */
    public static function addWorkers($name, callable $callback, $num, array $arguments = [])
    {
        $num = (int)max($num, 1);
        while ($num-- > 0) {
            self::addWorker("{$name}_$num", $callback, $arguments);
        }
    }

    protected static function runWorker(array $worker)
    {
        $pid = pcntl_fork();
        if (!$pid) {
            call_user_func_array($worker['callback'], $worker['arguments']);
            die();
        } elseif ($pid > 0) {
            self::$workersPid[$pid] = $worker['name'];
        }
    }

    public static function runWorkers()
    {
        foreach (self::$workers as $worker) {
            self::runWorker($worker);
        }
    }

    public static function kill($pid, $signo = null)
    {
        $signo = $signo ?? SIGSTOP;
        return posix_kill($pid, $signo);
    }

    public static function isSupportAsyncSignal()
    {
        return extension_loaded('pcntl') && function_exists('pcntl_async_singal');
    }

    protected static function installSignalHandle()
    {
        pcntl_signal(SIGINT, SIG_IGN);
//        pcntl_signal(SIGHUP, SIG_IGN); // kill
        pcntl_signal(SIGTERM, SIG_IGN);
    }

    public static function daemon($nochdir = false, $noclose = false)
    {
        global $STDIN, $STDOUT, $STDERR;

        $pid = pcntl_fork();
        if (!$pid) {
            $pid2 = pcntl_fork();
            if (!$pid2) {
                posix_setsid();
                if (!$nochdir) {
                    chdir('/');
                }
                if (!$noclose) {
                    fclose(STDIN);
                    fclose(STDOUT);
                    fclose(STDERR);
                    $STDIN = fopen(self::$logFilePath, 'ab');
                    $STDOUT = fopen(self::$logFilePath, 'ab');
                    $STDERR = fopen(self::$logFilePath, 'ab');
                }
                self::installSignalHandle();
                self::runWorkers();

                // main process SIGHUP restart handler
                pcntl_signal(SIGHUP, function ($signo) {
                    self::restart($_SERVER['argv']);
                });

                while (1) {
                    $status = 0;
                    $exitPid = pcntl_wait($status, WNOHANG);
                    if ($exitPid > 0 && isset(self::$workersPid[$exitPid])) {
                        $exitWorkerName = self::$workersPid[$exitPid];
                        unset(self::$workersPid[$exitPid]);
                        $exitWorker = self::$workers[$exitWorkerName];
                        self::runWorker($exitWorker);
                    }
                    pcntl_signal_dispatch();
                    Console::sleep(0.5);
                }
            } else {
                exit(0);
            }
        } else {
            exit(0);
        }
    }

    /**
     * 重启进程，向主进程发送SIGHUP信号
     *
     * @param array $argv index 0 is command
     */
    public static function restart(array $argv)
    {
        $statusCode = 0;
        // close child processes
        foreach (self::$workersPid as $cPid => $cName) {
            self::kill($cPid, SIGKILL);
            pcntl_wait($statusCode);
        }
        $argc = count($argv);
        if ($argc > 0) {
            $phpBinPath = Console::getCom()->config->app->phpBinPath;
            if ($phpBinPath) {
                Console::execCommand($phpBinPath, $argv, false, $result);
            }
        }
        exit($statusCode);
    }
}
