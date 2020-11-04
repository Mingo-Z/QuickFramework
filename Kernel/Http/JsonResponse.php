<?php
namespace Qf\Kernel\Http;

class JsonResponse extends Response
{
    protected $code;
    protected $message;
    protected $data;

    public function __construct(Request $request, $code = 0, $message = '', $data = '')
    {
        parent::__construct($request);

        $this->code = (int)$code;
        $this->message = $message;
        $this->data = $data;
    }

    public function setCode($code)
    {
        $this->code = (int)$code;

        return $this;
    }

    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    protected function getBody()
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'data' => $this->data,
            'timestamp' => getNowTimestampMs(),
        ];
    }

    protected function _sendContent()
    {
        $this->setJsonContent($this->getBody());
        parent::_sendContent();
    }
}