<?php
namespace Qf\Kernel;

use Qf\Kernel\Http\Dispatcher;
use Qf\Kernel\Http\Middleware\MiddlewareManager;
use Qf\Kernel\Http\Request;
use Qf\Kernel\Http\Response;
use Qf\Kernel\Http\Route\Router;
use Qf\Localization\Localization;
use Qf\Utils\FileHelper;

class Application
{
    /**
     * @var ComponentManager
     */
    protected static $com;
    protected static $appPath;
    protected static $app;

    /**
     * @var Request
     */
    public $request;
    /**
     * @var Response
     */
    public $response;

    /**
     * @var Dispatcher
     */
    public $dispatcher;

    protected static $locale;

    protected static function init()
    {
        define('AppPath', rtrim(self::$appPath, '/') . '/');
        require __DIR__ . '/../Configs/define.config.php';
        require FrameworkUtilsPath . 'functions.php';
        require FrameworkKernelPath . 'AutoClassLoader.php';
        AutoClassLoader::init();
        AutoClassLoader::setAutoLoadBasePath(FrameworkRootPath);
        AutoClassLoader::setAutoLoadBasePath(self::$appPath);

        self::$com = new ComponentManager();
        self::$com->configFile = FrameworkConfigsPath . 'components.config.php';

        ExceptionErrorHandle::installHandle();
        // vendor 第三方类库自动加载
        if (is_file(FrameworkVendorPath . 'autoload.php')) {
            FileHelper::includeFile(FrameworkVendorPath . 'autoload.php');
        }
        if (is_file(AppPath . 'vendor/autoload.php')) {
            FileHelper::includeFile(AppPath . 'vendor/autoload.php');
        }

        self::registerMiddleware();

        // 预加载应用全局公共文件
        if (self::$com->config->app->preloadIncludeFiles) {
            $appPreloadIncludeFiles = self::$com->config->app->preloadIncludeFiles->toArray();
            FileHelper::includeFiles($appPreloadIncludeFiles);
        }

        // Localized configuration
        self::$locale = new Localization(envIniConfig('locale', 'global', 'en'));
        $timezone = envIniConfig('timezone', 'global');
        if ($timezone) {
            date_default_timezone_set($timezone);
        }
    }

    protected static function registerMiddleware()
    {
        if (self::$com->config->app->middleware) {
            $middlewareConfigArray = self::$com->config->app->middleware->toArray();
            if ($middlewareConfigArray) {
                foreach ($middlewareConfigArray as $middlewareConfig) {
                    if (is_array($middlewareConfig) && isset($middlewareConfig[1])) {
                        $module = $middlewareConfig[2] ?? null;
                        MiddlewareManager::register($middlewareConfig[0], $middlewareConfig[1], $module);
                    }
                }
            }
        }
    }

    protected static function isInitialized()
    {
        if (!self::$app) {
            throw new Exception('Application uninitialized');
        }
    }

    /**
     * @return Localization
     */
    public static function getLocale()
    {
        self::isInitialized();
        return self::$locale;
    }

    protected function __construct($appPath)
    {
        self::$appPath = $appPath;
        static::init();
    }

    public static function getApp($appPath = null)
    {
        if (self::$app === null) {
            if (!$appPath || !is_dir($appPath)) {
                throw new Exception('Application project path must be a directory');
            }
            self::$app = new static($appPath);
        }

        return self::$app;
    }

    public function execute()
    {
        $this->request = (new Request())->init();
        $this->response = new Response($this->request);
        $router = Router::getInstance();
        $this->dispatcher = $router->getDispatcher();
        $this->dispatcher->dispatch()->execute();
    }

    public static function getCom()
    {
        self::isInitialized();
        return self::$com;
    }
}
