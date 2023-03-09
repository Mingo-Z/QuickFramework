<?php
namespace Qf\Utils;

use Qf\Kernel\Http\Request;
use Qf\Kernel\Http\Response;

/**
 * Server send events standard server implement
 */
class ServerSentEvents
{
    const MESSAGE_FILED_ID = 1;
    const MESSAGE_FILED_EVENT = 2;
    const MESSAGE_FILED_DATA = 3;
    const MESSAGE_FILED_RETRY = 4;

    public function setHeaders(Response $response)
    {
        $response->setContentType('text/event-stream')->setHeader('Cache-Control', 'no-cache')
            ->setHeader('Connection', 'keep-alive');

        return $this;
    }

    /**
     * @param mixed $content 消息内容
     * @param string $id 消息ID
     * @param string $event 自定义事件名称
     * @param int $retryInterval 客户端重连服务端时间间隔
     * @return string
     */
    public function encodeMessage($content = '', $id = null, $event = null, $retryInterval = null)
    {
        $message = null;
        if ($id) {
            $message = $this->encodeMessageLine(self::MESSAGE_FILED_ID, $id);
        }
        if ($event) {
            $message .= $this->encodeMessageLine(self::MESSAGE_FILED_EVENT, $event);
        }
        $content = json_encode($content);
        $message .= $this->encodeMessageLine(self::MESSAGE_FILED_DATA, $content);
        if ($retryInterval) {
            $retryInterval = (int)$retryInterval;
            if ($retryInterval > 0) {
                $message .= $this->encodeMessageLine(self::MESSAGE_FILED_RETRY, $retryInterval);
            }
        }
        $message .= "\n";

        return $message;
    }

    public function getLastEventID(Request $request)
    {
        return $request->getRequestHeader('Last-Event-ID');
    }

    /**
     * 响应客户端ping请求，用于保持连接
     *
     * @return string
     */
    public function getPongEvent()
    {
        return $this->encodeMessage('', null, 'pong');
    }

    /**
     * 通知客户端关闭连接
     *
     * @return string
     */
    public function getClosedEvent()
    {
        return $this->encodeMessage('', null, 'close');
    }

    /**
     * @param int $type 消息类型
     * @param mixed $value
     * @return string
     */
    protected function encodeMessageLine($type, $value)
    {
        switch ((int)$type) {
            case self::MESSAGE_FILED_ID:
                $line = "id: $value\n";
                break;
            case self::MESSAGE_FILED_EVENT:
                $line = "event: $value\n";
                break;
            case self::MESSAGE_FILED_DATA:
                $line = "data: $value\n";
                break;
            case self::MESSAGE_FILED_RETRY:
                $value = (int)$value;
                $line = "retry: $value\n";
                break;
            default:
                $line = '';
        }

        return $line;
    }
}
