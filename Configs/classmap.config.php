<?php
$appClassMapConfig = [];
$appClassMapFile = AppConfigsPath . 'classmap.config.php';
if (is_file($appClassMapFile)) {
    $appClassMapConfig = require $appClassMapFile;
}

return array_merge([

    ], $appClassMapConfig);
