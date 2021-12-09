<?php
namespace Qf\Event;

interface Listener
{
    public function handle(Event $event);
}
