<?php
namespace Qf\Components;

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

    protected static function signal()
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
                self::signal();
                self::runWorkers();
                while (1) {
                    $status = 0;
                    $exitPid = pcntl_wait($status);
                    $exitWorkerName = self::$workersPid[$exitPid];
                    unset(self::$workersPid[$exitPid]);
                    $exitWorker = self::$workers[$exitWorkerName];
                    self::runWorker($exitWorker);
                    time_nanosleep(0, 1000);
                }
            }
        }
    }
}