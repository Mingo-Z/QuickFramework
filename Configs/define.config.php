<?php
define('FrameworkRootPath', __DIR__ . '/../');
define('FrameworkKernelPath', FrameworkRootPath . 'Kernel/');
define('FrameworkConfigsPath', FrameworkRootPath . 'Configs/');
define('FrameworkUtilsPath', FrameworkRootPath . 'Utils/');
define('FrameworkVendorPath', FrameworkRootPath . 'vendor/');

//if (!defined('AppPath')) {
//    define('AppPath', FrameworkRootPath . '../');
//}
if (!defined('AppConfigsPath')) {
    define('AppConfigsPath', AppPath . 'configs/');
}

define('AppResourcePath', AppPath . 'public/resource/');

