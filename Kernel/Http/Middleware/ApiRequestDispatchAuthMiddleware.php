<?php
namespace Qf\Kernel\Http\Middleware;

use Closure;
use Qf\Kernel\Application;
use Qf\Kernel\Exception;

abstract class ApiRequestDispatchAuthMiddleware extends Middleware
{
    public static function handle(Application $app, Closure $next = null, $config = null)
    {
        $appKey = $config['appKey'] ?? envIniConfig('appKey', 'global');
        $moduleName = $app->dispatcher->getModuleName();
        $controllerName = $app->dispatcher->getControllerName();
        $actionName = $app->dispatcher->getActionName();
        $enableAuthMca = envIniConfig('enableAuthMca', 'http:auth', []);
        $isEnableAuth = true;
        $enableAuthKey = ':';
        if ($moduleName) {
            $enableAuthKey = $moduleName . $enableAuthKey;
        }
        // all request auth
        if (!isset($enableAuthMca['*:*:*'])) {
            if (!isset($enableAuthMca["{$enableAuthKey}*:*"])) {
                if (!isset($enableAuthMca["{$enableAuthKey}{$controllerName}:*"])) {
                    if (!isset($enableAuthMca["{$enableAuthKey}{$controllerName}:{$actionName}"])) {
                        $isEnableAuth = false;
                    }
                }
            }
        }

        if ($isEnableAuth) {
            $authKeyName = envIniConfig('authKeyName', 'http:auth');
            $serverAuthCode = $app->request->urlHashCode($appKey, [$authKeyName]);
            if ($app->request->isRequestJson()) {
                $clientAuthCode = $app->request->getOrigNoNormalValue($authKeyName);
            } else {
                $clientAuthCode = $app->request->$authKeyName;
            }
            if ($clientAuthCode !== $serverAuthCode) {
                $code = Exception::HTTP_STATUS_CODE_403;
                $message = 'Forbidden';
                $app->response->setCode($code);
                if ($app->request->isNeedJson()) {
                    $app->response->setJsonContent([
                        'businessCode' => $code,
                        'businessMessage' => $message,
                        'businessData' => '',
                        'timestamp' => getNowTimestampMs(),
                    ]);
                } else {
                    $app->response->setContent("$message($code)");
                }
                $app->response->setProcessed(true)->send()->stop();
            }
        }

        return parent::handle($app, $next, $config);
    }
}
