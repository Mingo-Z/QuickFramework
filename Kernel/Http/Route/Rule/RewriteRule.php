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
        $requestUri = trim($requestUri, '/');
        $uriBasePath = envIniConfig('uriBasePath', 'http');
        if ($uriBasePath) {
            $requestUri = str_replace($uriBasePath, '', $requestUri);
        }
        $requestUriArray = array_filter(explode('/', $requestUri));
        $moduleName = null;
        if (count($requestUriArray) >= 3) {
            $moduleName = isset($requestUriArray[0]) ? $requestUriArray[0] : null;
            $controllerName = isset($requestUriArray[1]) ? $requestUriArray[1] : null;
            $actionName = isset($requestUriArray[2]) ? $requestUriArray[2] : null;
        } else {
            $controllerName = isset($requestUriArray[0]) ? $requestUriArray[0] : null;
            $actionName = isset($requestUriArray[1]) ? $requestUriArray[1] : null;
        }

        return [
            'moduleName' => $moduleName,
            'controllerName' => $controllerName,
            'actionName' => $actionName,
        ];
    }
}
