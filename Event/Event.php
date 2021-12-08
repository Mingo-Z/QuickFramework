<?php
namespace Qf\Event;

abstract class Event
{
    /**
     * 事件ID
     *
     * @var int
     */
    protected $id;

    /**
     * 事件创建时间，单位：s
     *
     * @var int
     */
    protected $createdAt;

    /**
     * 事件上下文
     *
     * @var array
     */
    protected $context;

    /**
     * 停止传递
     *
     * @var bool
     */
    public $stopDelivery;

    /**
     * 是否是异步事件
     *
     * @var bool
     */
    protected $isAsync;

    /**
     * 监听者
     *
     * @var array
     */
    protected $listeners;

    public function getContext()
    {
        return $this->context;
    }

    public function getId()
    {
        return $this->id;
    }

    public function isAsync()
    {
        return $this->isAsync;
    }

    /**
     * @param string $listenerName Qf\Event\Listener类型的类名
     * @return $this
     */
    public function addListener($listenerName)
    {
        $this->listeners[] = $listenerName;

        return $this;
    }

    public function getListeners()
    {
        return $this->listeners;
    }

    public function __construct($id,  array $context = null, $isAsync = false)
    {
        $this->id = (int)$id;
        $this->context = $context;
        $this->isAsync = (bool)$isAsync;
        $this->createdAt = time();
        $this->listeners = [];
    }

}
