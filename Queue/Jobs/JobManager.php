<?php
namespace Qf\Queue\Jobs;

use Qf\Components\IdGeneratorProvider;
use Qf\Components\ProcessManagerProvider;
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

    public static function daemon(WorkerJob $job)
    {
        ProcessManagerProvider::addWorker($job->getName(), function () use ($job) {
            while (1) {
                $taskJob = $job->take();
                if ($taskJob) {
                    $isPcntlAlarmTimeout = false;
                    if ($taskJob->timeout > 0 && ProcessManagerProvider::isSupportAsyncSignal()) {
                        pcntl_async_signals(true);
                        pcntl_signal(SIGALRM, function () use ($taskJob) {
                            ExceptionErrorHandle::exceptionHandle(new JobException("Job exceeded the maximum running time of {$taskJob->timeout} seconds",
                                0, null, $taskJob));
                            die();
                        });
                        pcntl_alarm($taskJob->timeout);
                        $isPcntlAlarmTimeout = true;
                    }
                    self::runWorkerJob($taskJob);
                    if ($isPcntlAlarmTimeout) {
                        pcntl_alarm(0);
                    }
                } else {
                    time_nanosleep(0, 1000);
                }
            }
        });

        ProcessManagerProvider::daemon();
    }

    public static function runWorkerJob(WorkerJob $workerJob)
    {
        if (!$workerJob->work() && $workerJob->tries > $workerJob->getAttempts()) {
            $workerJob->release($workerJob->getDelay());
        }
    }
}
