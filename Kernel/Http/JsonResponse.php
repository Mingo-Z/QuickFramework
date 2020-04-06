<?php
namespace Qf\Kernel\Http;

class JsonResponse extends Response
{
    protected $businessCode;
    protected $businessCodeDesc;
    protected $data;

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

    public function setJsonContent($var)
    {
        return parent::setJsonContent($var);
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