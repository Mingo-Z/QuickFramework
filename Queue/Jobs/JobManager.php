<?php
namespace Qf\Queue\Jobs;

use Qf\Components\IdGeneratorProvider;
use Qf\Components\ProcessManagerProvider;

class JobManager
{
    /**
     * @param string $workerJobClass
     * @param string|null $name
     * @param array|null $body
     * @return WorkerJob
     * @throws JobException
     */
    public static function createWorkerJob($workerJobClass, $name = null, array $body = null)
    {
        $class = $workerJobClass;
        if (strncmp($class, 'Jobs', 4)) {
            $class = 'Jobs\\' . $class;
        }
        $classFile = AppPath . str_replace('\\', '/', $class) . '.php';
        if (is_file($classFile)) {
            require_once $classFile;
        }
        if (!class_exists($workerJobClass, false)) {
            throw new JobException("$class Job not exists");
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

        return $class::create($rawJob);
    }

    public static function daemon(WorkerJob $job)
    {
        ProcessManagerProvider::worker($job->getName(), function () use ($job) {
            while (1) {
                $taskJob = $job->take();
                if ($taskJob) {
                    if ($taskJob->timeout > 0 && ProcessManagerProvider::isSupportAsyncSignal()) {
                        pcntl_async_signals(true);
                        pcntl_signal(SIGALRM, function () use ($taskJob) {
                            ProcessManagerProvider::kill(posix_getpid());
                            throw new JobException("Job exceeded the maximum running time of {$taskJob->timeout} seconds",
                                0, null, $taskJob);
                        });
                        pcntl_alarm($taskJob->timeout);
                    }
                    self::runWorkerJob($taskJob);
                    pcntl_alarm(0);
                } else {
                    time_nanosleep(0, 1000);
                }
            }
        });

        ProcessManagerProvider::daemon();
    }

    public static function runWorkerJob(WorkerJob $workerJob)
    {
        $workerJob->work();
        if ($workerJob->tries > $workerJob->getAttempts()) {
            $workerJob->release($workerJob->getDelay());
        }
    }
}
