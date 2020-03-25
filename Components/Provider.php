<?php
namespace Qf\Components;

use Qf\Kernel\ComponentManager;

abstract class Provider
{
    /**
     * @var ComponentManager
     */
    protected $com;

    public function setComponentManager(ComponentManager $com)
    {
        $this->com = $com;
    }

    protected function encode($data)
    {
        return json_encode($data);
    }

    protected function decode($data)
    {
        return json_decode($data, true);
    }
}