<?php
namespace Qf\Queue\Jobs;

use Qf\Components\IdGeneratorProvider;
use Qf\Components\ProcessManagerProvider;
use Qf\Console\Console;
use Qf\Kernel\Exception;
use Qf\Kernel\ExceptionErrorHandle;

class JobManager
{
    /**
     * @param string $workerJobClass include all namespaces class name, eg: App\Jobs\MessageWorkerJob
     * @param string|null $name
     * @param array|null $body
     * @return WorkerJob
     * @throws JobException
     */
    public static function createWorkerJob($workerJobClass, $name = null, array $body = null)
    {
        if (!class_exists($workerJobClass, true)) {
            throw new JobException("$workerJobClass job class does not exists");
        }

        $idGenerator = new IdGeneratorProvider();
        $rawJob = [
            'id' => $idGenerator->getId(),
            'name' => $name ?? $workerJobClass,
            'body' => $body,
            'attempts' => 0,
            'timeout' => 0,
            'timeoutAt' => 0,
            'createdAt' => time(),
        ];

        return $workerJobClass::create($rawJob);
    }

    /**
     * 添加作业进程
     *
     * @param WorkerJob $job
     */
    public static function addWorker(WorkerJob $job)
    {
        ProcessManagerProvider::addWorker($job->getName(), function () use ($job) {
            while (1) {
                $taskJob = $job->take();
                if ($taskJob) {
                    $isPcntlAlarmTimeout = false;
                    if ($taskJob->timeout > 0) {
                        if (ProcessManagerProvider::isSupportAsyncSignal()) {
                            pcntl_async_signals(true);
                        }
                        pcntl_signal(SIGALRM, function () use ($taskJob) {
                            ExceptionErrorHandle::exceptionHandle(new JobException("Job exceeded the maximum running time of {$taskJob->timeout} seconds",
                                0, null, $taskJob));
                            exit(1);
                        });
                        pcntl_alarm($taskJob->timeout);
                        $isPcntlAlarmTimeout = true;
                    }
                    try {
                        self::runWorkerJob($taskJob);
                    } catch (Exception $e) {
                        ExceptionErrorHandle::exceptionHandle(new JobException($e->getMessage(), $e->getCode(), $e->getPrevious(), $taskJob));
                    }
                    if ($isPcntlAlarmTimeout) {
                        pcntl_alarm(0);
                    }
                } else {
                    Console::sleep(0.05);
                }
                if (!ProcessManagerProvider::isSupportAsyncSignal()) {
                    pcntl_signal_dispatch();
                }
            }
        });
    }

    public static function runWorkerJob(WorkerJob $workerJob)
    {
        if (!$workerJob->work() && $workerJob->tries > $workerJob->getAttempts()) {
            $workerJob->release($workerJob->getDelay());
        }
    }
}
