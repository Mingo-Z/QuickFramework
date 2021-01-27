<?php
namespace Qf\Kernel\Http\Middleware;

use Closure;
use Qf\Kernel\Application;
use Qf\Kernel\Exception;

class ApiRequestRateLimitMiddleware extends Middleware
{
    public static function handle(Application $app, Closure $next = null, $config = null)
    {
        if ($app->request->getData('requestStage') != MiddlewareManager::TRIGGER_STAGE_HTTP_DISPATCH) {
            throw new Exception('ApiRequestRateLimitMiddleware can only run in the dispatch phase of the 
            request[MiddlewareManager::TRIGGER_STAGE_HTTP_DISPATCH]');
        }
        $dispatcher = $app->dispatcher;
        $requestUri = sprintf('/%s/%s/%s', $dispatcher->getModuleName(), $dispatcher->getControllerName(), $dispatcher->getActionName());
        $limiter = $app::getCom()->httpRequestRateLimiter;
        if (!$limiter->isAllow($requestUri)) {
            $code = Exception::HTTP_STATUS_CODE_403;
            $message = 'Forbidden: Request rate limit';
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
        return parent::handle($app, $next, $config);
    }
}