<?php
namespace Qf\Kernel\Http;

class JsonResponse extends Response
{
    protected $businessCode;
    protected $businessCodeDesc;

    public function setBusinessCodeAndDesc($code = 0, $desc = '')
    {
        $this->businessCode = (int)$code;
        $this->businessCodeDesc = $desc;

        return $this;
    }

    public function setContent($content)
    {
        return $this->setJsonContent($content);
    }

    public function setJsonContent($content)
    {
        $this->setContentType('application/json');
        $this->content = $content;
        return $this;
    }

    protected function getJsonBody()
    {
        return json_encode([
            'businessCode' => $this->businessCode,
            'businessCodeDesc' => $this->businessCodeDesc,
            'data' => $this->content,
        ]);
    }

    protected function _sendContent()
    {
        $this->content = $this->getJsonBody();
        parent::_sendContent();
    }
}