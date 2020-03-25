<?php
$appConfigManagerConfig = [];
$appConfigManagerFile = AppConfigsPath . 'components.config.php';
if (is_file($appConfigManagerFile)) {
    $appConfigManagerConfig = require $appConfigManagerFile;
}

return array_merge([
        'app' => [
            'configFilePath' => AppConfigsPath . 'app.config.php',
            'type' => \Qf\Components\ConfigFileManagerProvider::CONFIG_FILE_TYPE_PHP_ARRAY,
        ]
    ], $appConfigManagerConfig);