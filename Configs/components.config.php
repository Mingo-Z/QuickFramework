<?php
$appComponentsConfig = [];
$appComponentsConfigFile = AppConfigsPath . 'components.config.php';
if (is_file($appComponentsConfigFile)) {
    $appComponentsConfig = require $appComponentsConfigFile;
}

return array_merge([
        'errlog' => [
            'className' => Qf\Components\Log\LogManager::class,
            'initProperties' => [
                'runLogLevel' => Qf\Components\Log\LogManager::LOG_LEVEL_NOTICE,
                'driverType' => 'file',
                'options' => [
                    'storagePath' => envIniConfig('path', 'log', AppPath . 'logs/'),
                    'filePrefix' => envIniConfig('errFilePrefix', 'log',  'error_'),
                ]
            ]
        ],
        'cache' => [
            'className' => Qf\Components\Redis\RedisCacheProvider::class,
            'initProperties' => [
                'configFile' => AppConfigsPath . 'servers/redis.config.php',
                'connectTimeout' => 3,
                'name' => '',
            ]
        ],
        'database' => [
            'className' => Qf\Components\MysqlDistributedProvider::class,
            'initProperties' => [
                'dbConfigFile' => AppConfigsPath . 'servers/mysql/default.config.php',
                'tablesConfigFile' => AppConfigsPath . 'servers/mysql/tables.config.php',
            ],
        ],
        'runlog' => [
            'className' => Qf\Components\Log\LogManager::class,
            'initProperties' => [
                'runLogLevel' => Qf\Components\Log\LogManager::LOG_LEVEL_DEBUG,
                'driverType' => 'file',
                'options' => [
                    'storagePath' => envIniConfig('path', 'log', AppPath . 'logs/'),
                    'filePrefix' => envIniConfig('runFilePrefix', 'log',  'run_'),
                ]
            ]
        ],
        'sqllog' => [
            'className' => Qf\Components\Log\LogManager::class,
            'initProperties' => [
                'runLogLevel' => Qf\Components\Log\LogManager::LOG_LEVEL_DEBUG,
                'driverType' => 'file',
                'options' => [
                    'storagePath' => envIniConfig('path', 'log', AppPath . 'logs/'),
                    'filePrefix' => envIniConfig('sqlFilePrefix', 'log',  'sql_'),
                ]
            ]
        ],
        'config' => [
            'className' => Qf\Components\ConfigFileManagerProvider::class,
            'initProperties' => [
                'configCoreFilePath' => FrameworkConfigsPath . 'configmanager.config.php',
            ]
        ],
        'idgenerator' => [
            'className' => \Qf\Components\IdGeneratorProvider::class,
            'initProperties' => [
                'machineId' => 0,
                'startTimestampTs' => 1545387360555,
            ]
        ],
    ], $appComponentsConfig);