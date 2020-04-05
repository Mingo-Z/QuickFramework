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

    protected $isUrlRewrite;
    protected $uriBasePath;

    protected $app;
    protected $isProcessed;

    private function __construct(array $config, Application $app)
    {
        $this->defaultModuleName = isset($config['defaultModuleName']) ? $config['defaultModuleName'] : null;
        $this->defaultControllerName = isset($config['defaultControllerName']) ? $config['defaultControllerName'] : 'Index';
        $this->defaultActionName = isset($config['defaultActionName']) ? $config['defaultActionName'] : 'Index';
        $this->isUrlRewrite = isset($config['isUriRewrite']) ? (bool)$config['isUriRewrite'] : false;
        $this->app = $app;
        $this->isProcessed = true;
    }

    /**
     * 初始实例化该类
     *
     * @param boolean $isRewrite 是否开启URI重写
     * @param string $modulesPath module文件基础路径
     * @return Dispatcher
     */
    public static function getInstance(array $config, Application $app)
    {
        static $instance;
        if (!$instance) {
            $instance = new self($config, $app);
        }
        return $instance;
    }

    public function getControllerName()
    {
        return $this->controllerName;
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

    /**
     * 获取当前请求action
     *
     * @return string
     */
    public function getActionName()
    {
        return $this->actionName;
    }

    public function setProcessed($bool = true)
    {
        $this->isProcessed = $bool;
    }

    /**
     * URI解析验证module、action
     */
    public function dispatch()
    {
        $moduleName = '';
        $controllerName = '';
        $actionName = '';
        $requestUri = $_SERVER['REQUEST_URI'];
        if (($pos = strpos($requestUri, '?')) !== false) {
            $requestUri = substr($requestUri, 0, $pos);
        }
        if (!$this->isUrlRewrite) {
            $moduleName = isset($_REQUEST['m']) ? $_REQUEST['m'] : '';
            $controllerName = isset($_REQUEST['c']) ? $_REQUEST['c'] : '';
            $actionName = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        } else {
            if ($this->uriBasePath) {
                $requestUri = str_replace($this->uriBasePath, '', $requestUri);
            }
            $requestUri = trim($requestUri, '/');
            $requestUriArray = array_filter(explode('/', $requestUri));
            if (count($requestUriArray) >= 3) {
                $moduleName = isset($requestUriArray[0]) ? $requestUriArray[0] : '';
                $controllerName = isset($requestUriArray[1]) ? $requestUriArray[1] : '';
                $actionName = isset($requestUriArray[2]) ? $requestUriArray[2] : '';
            } else {
                $controllerName = isset($requestUriArray[0]) ? $requestUriArray[0] : '';
                $actionName = isset($requestUriArray[1]) ? $requestUriArray[1] : '';
            }
        }
        $this->moduleName = $moduleName ?: $this->defaultModuleName;
        $this->controllerName = $controllerName ?: $this->defaultControllerName;
        $this->actionName = $actionName ?: $this->defaultActionName;

        if (!$this->controllerName || !$this->actionName) {
            throw new Exception("Request uri: $requestUri resource does not exist", Exception::HTTP_STATUS_CODE_404);
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