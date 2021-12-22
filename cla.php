<?php
use Qf\Console\Console;
use Qf\Console\Command;
use Qf\Components\ProcessManagerProvider;
use Qf\Event\EventManager;
use Qf\Queue\Jobs\JobManager;

if (($mode = php_sapi_name()) != 'cli') {
    exit("cla must running in command line mode, current mode is $mode\n");
}
$appRootPath = null;
foreach ($_SERVER['argv'] as $index => $option) {
    $nextOption = $_SERVER['argv'][$index + 1] ?? null;
    if (($option == '-r' || $option == '--root') && $nextOption && $nextOption[0] != '-') {
        $appRootPath = $nextOption;
        break;
    }
}
if (!$appRootPath || !is_dir($appRootPath)) {
    exit("application root path does not exist");
}
require __DIR__ . '/Console/Console.php';
$app = Console::getApp($appRootPath);

$command = new Command();
$command->setName('cla')
    ->setOption('M', 'mode', true,
    true, true, 'api|event|job', 'api', 'Choose running mode')
    ->setOption('d', 'daemon', false,
        false, false, null, null, 'Is run in daemon mode');
$command->parse($_SERVER['argv']);
$isDaemon = $command->getOptionValue('d') ?? false;
$runningMode= $command->getOptionValue('M') ?? 'api';

switch ($runningMode) {
    case 'event':
        ProcessManagerProvider::addWorker('eventLoop', function () {
            while (1) {
                EventManager::getInstance()->process();
                Console::sleep(0.5);
            }
        });
        break;
    case 'job':
        $classes = JobManager::listAppWorkerJobClasses();
        foreach ($classes as $class) {
            JobManager::addWorker(JobManager::createWorkerJob($class));
        }
        break;
    default:
        $app->execute();
}

if (in_array($runningMode, ['event', 'job'])) {
    if ($isDaemon) {
        ProcessManagerProvider::daemon();
    } else {
        ProcessManagerProvider::runWorkers();
    }
}





