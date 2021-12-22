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
    exit("application root path does not exist, use -r|--root path");
}
require __DIR__ . '/Console/Console.php';
$app = Console::getApp($appRootPath);

$command = new Command();
$command->setName('cla')
    ->setOption('h', 'help', false,
        false, false, null, null, 'Print usage information')
    ->setOption('f', 'func', false,
    true, true, 'api|event|job', 'api', 'Choose functional module')
    ->setOption('d', 'daemon', false,
        false, false, null, null, 'Is run in daemon mode for functional module event,job...')
    ->setOption('l', 'list', false,
        true, false, 'event|job', null, 'List all event or job');
$command->parse($_SERVER['argv']);

$listAction = $command->getOptionValue('l');
if (in_array($listAction, ['event', 'job'])) {
    $message = '';
    if ($listAction == 'event') {
        $eventConfig = Console::getCom()->config->app->event->toArray();
        if ($eventConfig) {
            $eventListeners = $eventConfig['listeners'] ?? [];
            foreach ($eventListeners as $event => $listeners) {
                $message .= "Listeners for event $event:\n";
                foreach ($listeners as $listener) {
                    $message .= "\t$listener\n";
                }
            }
        }
    } else {
        $message = "Worker jobs list:\n";
        foreach (JobManager::listAppWorkerJobClasses() as $class) {
            $message .= "\t$class\n";
        }

    }
    Console::response($message);
}

$funcModule= $command->getOptionValue('M') ?? 'api';
switch ($funcModule) {
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

if (in_array($funcModule, ['event', 'job'])) {
    $isDaemon = $command->getOptionValue('d') ?? false;
    if ($isDaemon) {
        ProcessManagerProvider::daemon();
    } else {
        ProcessManagerProvider::runWorkers();
    }
}





