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
        if (!is_resource($var)) {
            $this->content = $var;
            $this->setContentType('application/json');
        }

        return $this;
    }


    protected function _sendContent()
    {
        $this->content = json_encode([
            'code' => $this->businessCode,
            'desc' => $this->businessCodeDesc,
            'data' => $this->content,
        ]);
        parent::_sendContent();
    }
}