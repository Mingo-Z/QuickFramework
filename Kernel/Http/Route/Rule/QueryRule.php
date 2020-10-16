<?php
namespace Qf\Kernel\Http\Route\Rule;

use Qf\Kernel\Application;

class QueryRule extends Rule
{
    protected static function resolve(array $options = null)
    {
        $request = Application::getApp()->request;
        $moduleKey = envIniConfig('queryModuleKey', 'http', 'm');
        $controllerKey = envIniConfig('queryControllerKey', 'http', 'c');
        $actionKey = envIniConfig('queryActionKey', 'http', 'action');

        $moduleName = $request->$moduleKey;
        $controllerName = $request->$controllerKey;
        $actionName = $request->$actionKey;

        return [
            'moduleName' => $moduleName,
            'controllerName' => $controllerName,
            'actionName' => $actionName,
        ];
    }
}
