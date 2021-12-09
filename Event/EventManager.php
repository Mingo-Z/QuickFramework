<?php
namespace Qf\Event;

use Qf\Components\Facades\IdGenerator;
use Qf\Kernel\Application;
use Qf\Kernel\Exception;
use Qf\Kernel\ShutdownScheduler;

class EventManager
{
    /**
     * 事件监听者
     *
     * @var array
     */
    protected $registeredEventListeners;

    /**
     * 同步事件队列
     *
     * @var \SplQueue
     */
    protected $syncEventQueue;

    public static function getInstance()
    {
        static $instance = null;

        if (!$instance) {
            $instance = new self();
        }

        return $instance;
    }

    public function triggerEvent($eventName, array $context = null, $isAsync = false)
    {
        $this->isEvent($eventName);
        $event = new $eventName(self::getEventId(), $context, $isAsync);
        foreach ($this->getEventListeners($eventName) as $listener) {
            $event->addListener($listener);
        }
        if (!$isAsync) {
            $this->syncEventQueue->push($event);
        } else {
            $this->getAsyncQueue()->rPush(serialize($event));
        }
    }

    public function process()
    {
        if (isPhpCommandMode()) {
            while (($serializeEvent = $this->getAsyncQueue()->lPop())) {
                $event = unserialize($serializeEvent);
                $this->handleEvent($event);
            }
        } else {
            while (($event = $this->syncEventQueue->pop())) {
                $this->handleEvent($event);
            }
        }
    }

    protected function handleEvent(Event $event)
    {
        $listeners = $event->getListeners();
        foreach ($listeners as $listenerName) {
            $listener = new $listenerName(); // Extends Listener class
            $listener->handle($event);
        }
    }

    protected function getAsyncQueue()
    {
        $queue = null;

        if (!$queue) {
            $eventConfig = Application::getCom()->config->app->event;
            if (!$eventConfig || !$eventConfig->queueComponentName
                || !($queue = Application::getCom()->{$eventConfig->queueComponentName})) {
                throw new Exception('Asynchronous event queue component configuration error');
            }
        }

        return $queue;
    }

    protected function __construct()
    {
        $this->registeredEventListeners = [];
        $this->syncEventQueue = new \SplQueue();
        $this->registeredEventDrivers = [];

        if (Application::getCom()->config->app->event) {
            $eventListeners = Application::getCom()->config->app->event->listeners->toArray();
            foreach ($eventListeners as $eventName => $listeners) {
                foreach ($listeners as $listenerName) {
                    $this->addEventListener($eventName, $listenerName);
                }
            }
        }
        if (!isPhpCommandMode()) {
            ShutdownScheduler::registerCallback([$this, 'process']);
        }
    }

    /**
     * @param string $eventName Qf\Event\Event类型的类名
     * @param string $listenerName Qf\Event\Listener类型的类名
     * @throws Exception
     * @return EventManager
     */
    public function addEventListener($eventName, $listenerName)
    {
        $this->isEvent($eventName);
        $this->isListener($listenerName);
        $this->registeredEventListeners[$eventName][$listenerName] = $listenerName;

        return $this;
    }

    protected function isEvent($name)
    {
        if (!is_a($name, Event::class, true)) {
            throw new Exception("Event $name not exists");
        }
    }

    protected function isListener($name)
    {
        if (!is_a($name, Listener::class, true)) {
            throw new Exception("Event $name not exists");
        }
    }

    /**
     * @param string $eventName Qf\Event\Event类型的类名
     * @param string $listenerName Qf\Event\Listener类型的类名
     * @return void
     */
    public function removeEventListener($eventName, $listenerName)
    {
        if (isset($this->registeredEventListeners[$eventName]) && isset($this->registeredEventListeners[$eventName][$listenerName])) {
            unset($this->registeredEventListeners[$eventName][$listenerName]);
        }
    }

    /**
     * @param string $eventName Qf\Event\Event类型的类名
     * @return array
     */
    protected function getEventListeners($eventName)
    {
        return $this->registeredEventListeners[$eventName] ?? [];
    }

    protected static function getEventId()
    {
        return IdGenerator::getId();
    }
}
