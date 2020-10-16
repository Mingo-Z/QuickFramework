<?php
namespace Qf\Kernel\Http;
use Qf\Kernel\Exception;
use Qf\Kernel\Application;
use Qf\Kernel\Http\Middleware\MiddlewareManager;

class Dispatcher
{
    protected $moduleName;
    protected $controllerName;
    protected $actionName;

    protected $defaultModuleName;
    protected $defaultControllerName;
    protected $defaultActionName;

    protected $app;
    protected $isProcessed;

    private function __construct(array $options = null)
    {
        $this->defaultModuleName = envIniConfig('defaultModuleName', 'http');
        $this->defaultControllerName = envIniConfig('defaultControllerName', 'http', 'Index');
        $this->defaultActionName = envIniConfig('defaultActionName', 'http', 'Index');
        $this->app = Application::getApp();
        $this->isProcessed = true;
    }

    /**
     * 初始实例化该类
     *
     * @return Dispatcher
     */
    public static function getInstance(array $options = null)
    {
        static $instance;
        if (!$instance) {
            $instance = new self($options);
        }
        return $instance;
    }

    public function getControllerName()
    {
        return $this->controllerName;
    }

    public function setControllerName($name)
    {
        if ($name) {
            $this->controllerName = $name;
        }

        return $this;
    }

    /**
     * 获取当前请求
     *
     * @return string
     */
    public function getModuleName()
    {
        return $this->moduleName;
    }

    public function setModuleName($name)
    {
        if ($name) {
            $this->moduleName = $name;
        }

        return $this;
    }

    /**
     * 获取当前请求action
     *
     * @return string
     */
    public function getActionName()
    {
        return $this->actionName;
    }

    public function setActionName($name)
    {
        if ($name) {
            $this->actionName = $name;
        }

        return $this;
    }

    public function setProcessed($bool = true)
    {
        $this->isProcessed = $bool;
    }

    /**
     * URI解析验证module、controller、action
     */
    public function dispatch()
    {
        $this->moduleName = $this->moduleName ?? $this->defaultModuleName;
        $this->controllerName = $this->controllerName ?? $this->defaultControllerName;
        $this->actionName = $this->actionName ?? $this->defaultActionName;

        if (!$this->controllerName || !$this->actionName) {
            throw new Exception("Request uri: {$_SERVER['REQUEST_URI']} resource does not exist", Exception::HTTP_STATUS_CODE_404);
        }
        if ($this->moduleName) {
            $this->moduleName = ucfirst(strtolower($this->moduleName));
        }

        MiddlewareManager::triggerMiddleware(MiddlewareManager::TRIGGER_STAGE_HTTP_ROUTE, $this->app, $this->moduleName);

        return $this;
    }

    public function execute()
    {
        if ($this->isProcessed) {
            $class = "Http\\{$this->controllerName}Controller";
            if ($this->moduleName) {
                $class = $this->moduleName . '\\' . $class;
            }
            $class = 'App\\' . $class;
            if (!class_exists($class, true) || !is_subclass_of($class, Controller::class)) {
                throw new Exception("Application controller $class class does not exists", Exception::HTTP_STATUS_CODE_404);
            }
            $controllerInstance = new $class($this->app);
            $result = $controllerInstance->{$this->actionName}();
            $response = $this->app->response;
            $resIsResponseObj = false;
            if (!is_null($result)) {
                if (is_object($result)) {
                    if ($result instanceof Response) {
                        $response = $result;
                        $resIsResponseObj = true;
                    } elseif (method_exists($result, '__toString')) {
                        $result = (string)$result;
                    }
                }
                if (is_scalar($result)) { // int,float,string,bool
                    $response->setContent($result);
                } elseif (!$resIsResponseObj) {
                    $response->setJsonContent($result);
                }
            }
            MiddlewareManager::triggerMiddleware(MiddlewareManager::TRIGGER_STAGE_HTTP_RESPONSE_BEFORE, $this->app, $this->moduleName);

            $response->setProcessed(true)->send();

            MiddlewareManager::triggerMiddleware(MiddlewareManager::TRIGGER_STAGE_HTTP_RESPONSE_AFTER, $this->app, $this->moduleName);

        }
    }
}