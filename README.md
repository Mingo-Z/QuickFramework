# QuickFramework
- PHP fast development framework!
- Require PHP version 7.1+

***
## Quick start
```php
<?php
    const APP_ROOT_PATH = __DIR__ . '/';
    require_once APP_ROOT_PATH . '../QuickFramework/Kernel/Application.php';
    
    $app = Qf\Kernel\Application::getApp(APP_ROOT_PATH);
    $app->execute();  
```

