<?php
namespace Qf\Kernel\Http;

use Qf\Kernel\Application;
use Qf\Kernel\ComponentManager;
use Qf\Kernel\Exception;

abstract class Controller
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var ComponentManager;
     */
    protected $com;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->com = Application::getCom();
    }

    public function __call($method, array $arguments)
    {
        $methodName = $method . 'Action';
        if (method_exists($this, $methodName)) {
            $beforeMethod = "before$method";
            $afterMethod = "after$method";
            if (method_exists($this, $beforeMethod)) {
                $this->$beforeMethod();
            }
            $result = $this->$methodName(...$arguments);
            if (method_exists($this, $afterMethod)) {
                $this->$afterMethod();
            }

            return $result;
        } else {
            throw new Exception('Controller ' . static::class. "::$methodName method does not exists", Exception::HTTP_STATUS_CODE_404);
        }
    }


}