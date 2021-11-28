<?php
namespace Qf\Kernel\Http;

use Qf\Kernel\Application;

abstract class ApiController extends Controller
{
    protected $internalErrorTable = [
        0 => 'Success',
        1 => 'Failure',
        2 => 'The parameter is wrong or the type does not match',
        3 => 'Auth code error',
        4 => 'Operation failed, please try again later',
    ];

    protected $businessErrorTable = [];
    protected $code;
    protected $message;
    protected $data;

    public function __construct(Application $app)
    {
        parent::__construct($app);

        $this->code = 0;
        $this->message = '';
    }

    protected function addBusinessErrorTableItem($code, $message)
    {
        $this->businessErrorTable[(int)$code] = (string)$message;
    }

    protected function setCodeAndMessage($code, $message = '')
    {
        $this->code = (int)$code;
        $this->message = (string)$message;

        return $this;
    }

    protected function setResponseJsonBody()
    {
        if (!$this->message) {
            $this->message = $this->internalErrorTable[$this->code] ?? $this->businessErrorTable[$this->code] ?? '';
        }
        $body = [
            'code' => $this->code,
            'message' => translate($this->message),
            'data' => $this->data,
            'timestamp' => getNowTimestampMs(),
        ];

        return $this->app->response->setJsonContent($body);
    }

}

