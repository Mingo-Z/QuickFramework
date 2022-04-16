<?php
namespace Qf\Kernel\Http\Route;

use Qf\Kernel\Exception;
use Qf\Kernel\Http\Dispatcher;
use Qf\Kernel\Http\Middleware\MiddlewareManager;
use Qf\Kernel\Http\Route\Rule\QueryRule;
use Qf\Kernel\Http\Route\Rule\RewriteRule;

class Router
{
    protected static $registeredRules = [];

    protected static $internalRules = [
        'Query' => QueryRule::class,
        'Rewrite' => RewriteRule::class,
    ];

    protected function __construct()
    {
        foreach (self::$internalRules as $name => $class) {
            self::registerRule($name, $class);
        }
    }

    public static function getInstance()
    {
        static $instance;
        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    public function registerRule($name, $class)
    {
        self::$registeredRules[$name] = $class;
    }

    protected function getRuleClass($name)
    {
        if (!isset(self::$registeredRules[$name])) {
            throw new Exception("$name rule parser is not registered");
        }

        return self::$registeredRules[$name];
    }

    public function unregisterRule($name)
    {
        unset(self::$registeredRules[$name]);
    }

    /**
     * @return Dispatcher
     * @throws Exception
     */
    public function getDispatcher()
    {
        MiddlewareManager::triggerMiddleware(MiddlewareManager::TRIGGER_STAGE_HTTP_ROUTE);

        // load custom route rules file
        if (is_file(AppPath . 'route.php')) {
            include AppPath . 'route.php';
        }

        // http route
        $ruleName = envIniConfig('routeRuleName', 'http', 'Query');
        $ruleOptions = envIniConfig('ruleOptions', 'http');
        $rule = $this->getRuleClass($ruleName);
        $routeComponents = $rule::getRouteComponents($ruleOptions);
        $dispatcher = Dispatcher::getInstance();
        if ($routeComponents['isMCA']) {
            $dispatcher->setModuleName($routeComponents['moduleName'])->setControllerName($routeComponents['controllerName'])
                ->setActionName($routeComponents['actionName']);
        } else {
            $dispatcher->setCustomPathHandler($routeComponents['customPathHandler']);
        }

        return $dispatcher;
    }

}

