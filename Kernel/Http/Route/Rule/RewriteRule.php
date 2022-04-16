<?php
namespace Qf\Kernel\Http\Route\Rule;

use Qf\Kernel\Application;

class RewriteRule extends Rule
{
    /**
     * @return array|null
     * @throws \Qf\Kernel\Exception
     */
    protected static function resolve(array $options = null)
    {
        $request = Application::getApp()->request;
        $requestUri = $request->getServer('request_uri');
        if (($pos = strpos($requestUri, '?')) !== false) {
            $requestUri = substr($requestUri, 0, $pos);
        }
        $origRequestUri = $requestUri;
        $requestUri = trim($requestUri, '/');
        $uriBasePath = envIniConfig('uriBasePath', 'http');
        if ($uriBasePath) {
            $requestUri = str_replace($uriBasePath, '', $requestUri);
        }

        $moduleName = null;
        $controllerName = null;
        $actionName = null;
        // 自定义路由规则
        $isMCA = true;
        $customPathHandler = null;
        if (($config = self::getPathHandlerConfig($origRequestUri, false))) {
            $isMCA = $config['isMCA'];
            $handler = $config['handler'];
            if ($isMCA) {
                list($moduleName, $controllerName, $actionName) = $handler;
            } else {
                $customPathHandler = $handler;
            }
        } else {
            $requestUriArray = array_filter(explode('/', $requestUri));
            if (count($requestUriArray) >= 3) {
                $moduleName = $requestUriArray[0] ?? null;
                $controllerName = $requestUriArray[1] ?? null;
                $actionName = $requestUriArray[2] ?? null;
            } else {
                $controllerName = $requestUriArray[0] ?? null;
                $actionName = $requestUriArray[1] ?? null;
            }
        }

        return [
            'isMCA' => $isMCA,
            'customPathHandler' => $customPathHandler,
            'moduleName' => $moduleName,
            'controllerName' => $controllerName,
            'actionName' => $actionName,
        ];
    }
}

