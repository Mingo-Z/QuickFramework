<?php
namespace Qf\Kernel\Http;

use Qf\Components\Facades\Cookie;
use Qf\Kernel\RuntimeContainer;
use Qf\Utils\OtherHelper;
use Qf\Utils\UserAgentHelper;

class Request
{
    const HTTP_REQUEST_METHOD_POST = 'POST';
    const HTTP_REQUEST_METHOD_GET = 'GET';
    const HTTP_REQUEST_METHOD_PUT = 'PUT';

    const HTTP_REQUEST_VAR_GET = 0;
    const HTTP_REQUEST_VAR_POST = 1;
    const HTTP_REQUEST_VAR_COOKIE = 2;
    const HTTP_REQUEST_VAR_SERVER = 3;
    const HTTP_REQUEST_VAR_SESSION = 4;

    /**
     * 0 => $_POST 1=$_GET 2=COOKIE 3=SERVER 4=SESSION
     *
     * @var array
     */
    protected $origGpcssVarArray;

    protected $filteredPostVar;
    protected $filteredGetVar;
    /**
     * $_GET,$_POST merge
     *
     * @var array
     */
    protected $filteredGetPostVar;

    protected $filteredArgv;

    /**
     * 过滤后的用户cookie，为重用Cookie类实现
     * 暂时不使用
     *
     * @var array
     */
    protected $filteredCookieVar;
    protected $filteredServerVar;

    protected $requestHeaders;

    public function __construct()
    {
        $this->filteredPostVar = [];
        $this->filteredGetVar = [];
        $this->filteredCookieVar = [];
        $this->filteredServerVar = [];
        $this->filteredArgv = [];
        $this->requestHeaders = [];

        $this->origGpcssVarArray = [];
    }

    public function setData($key, $value)
    {
        RuntimeContainer::set($key, $value, 'request');
    }

    public function getData($key, $default = null)
    {
        return RuntimeContainer::get($key, 'request', $default);
    }

    public function init()
    {
        if (!$this->isCli()) {
            $this->origGpcssVarArray = [
                self::HTTP_REQUEST_VAR_GET => $_GET,
                self::HTTP_REQUEST_VAR_POST => $_POST,
                self::HTTP_REQUEST_VAR_COOKIE => $_COOKIE,
                self::HTTP_REQUEST_VAR_SERVER => $_SERVER,
            ];
            if (isset($_SESSION)) {
                $this->origGpcssVarArray[self::HTTP_REQUEST_VAR_SESSION] = $_SESSION;
            }
            $this->filteredPostVar = $this->filterArrayVar($_POST);
            $this->filteredGetVar = $this->filterArrayVar($_GET);
            $this->filteredGetPostVar = array_merge($this->filteredGetVar, $this->filteredPostVar);
            $this->filteredCookieVar = $this->filterArrayVar($_COOKIE);
        } else {
            $this->origGpcssVarArray = [
                self::HTTP_REQUEST_VAR_SERVER => $_SERVER,
            ];
            $this->filteredArgv = $this->filterArrayVar($_SERVER['argv']);
        }
        $this->filteredServerVar = $this->filterArrayVar($_SERVER);
        $this->requestHeaders = $this->parseRequestAllHeadersByServer();

        return $this;
    }

    public function getOrigGpcssVar($key, $type = self::HTTP_REQUEST_VAR_GET, $default = null)
    {
        $ret = $default;

        if (isset($this->origGpcssVarArray[$type]) && isset($this->origGpcssVarArray[$type][$key])) {
            $ret = $this->origGpcssVarArray[$type][$key];
        }

        return $ret;
    }

    public function getOrigGpcssArray($type = self::HTTP_REQUEST_VAR_GET)
    {
        return isset($this->origGpcssVarArray[$type]) ? $this->origGpcssVarArray[$type] : null;
    }

    /**
     * @return UserAgentHelper
     */
    public function getUserAgentObject()
    {
        return UserAgentHelper::getInstance($this->getRequestHeader('user_agent'));
    }

    /**
     * 通过该magic方法，快捷获取过滤后的$_GET,$_POST数组
     * 指定元素的值
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return isset($this->filteredGetPostVar[$name]) ? $this->filteredGetPostVar[$name] : null;
    }

    /**
     * 过滤HTTP请求的用户侧信息
     *
     * @param array $fields 待过滤的数组，引用传入
     * @return array
     */
    protected function filterArrayVar(array $fields)
    {
        $filteredArray = array();
        $searchKws = array('&', '"', "'", '<', '>', "\r", '\\', "\n",);
        $replaceKws = array('&amp;', '&quot;', '&#039;', '&lt;', '&gt;', '', '&#092;', '',);
        $phpGlobalVarNames = array(
            '_POST',
            '_GET',
            '_COOKIE',
            '_SESSION',
            '_SERVER',
            '_REQUEST',
            'GLOBALS',
            '_FILES',
            '_ENV',
            'argc',
            'argv',
        );
        foreach ($fields as $key => $value) {
            // 严格类型比较，防止自动类型转换
            if (!in_array($key, $phpGlobalVarNames, true)) {
                $filteredKey = str_replace($searchKws, $replaceKws, $key);
                if (is_array($value)) {
                    $filteredArray[$filteredKey] = $this->filterArrayVar($value);
                } elseif (is_string($value)) {
                    $filteredArray[$filteredKey] = str_replace($searchKws, $replaceKws, $value);
                }
            } else {
                // 防止PHP保留变量被污染，在传入的原始数组中删除
                unset($fields[$key]);
            }
        }
        return $filteredArray;
    }

    public function getDateModified()
    {
        return $this->getRequestHeader('if-modified-since');
    }

    public function getEtag()
    {
        return $this->getRequestHeader('if-none-match');
    }

    /**
     * 获取客户端IP
     *
     * @param string $ipVarKey 指定获取哪类型IP
     * @return string
     */
    public function getClientIp($ipVarKey = '')
    {
        $ipAddr = 'unknown';
        if ($ipVarKey) {
            $ipAddr = $this->getServer($ipVarKey);
        } else {
            if (($forwardedIps = $this->getServer('HTTP_X_FORWARDED_FOR'))) {
                $ipAddrs = explode(',', $forwardedIps);
                $ipAddr = $ipAddrs[0];
            } elseif (!($ipAddr = $this->getServer('REMOTE_ADDR'))) {
                $ipAddr = $this->getServer('HTTP_CLIENT_IP');
            }
        }
        return $ipAddr;
    }

    public function getCookie($varName)
    {
        return Cookie::get($varName);
    }

    public function getArgv()
    {
        return $this->filteredArgv;
    }

    /**
     * 获取所有post参数
     *
     * @return array
     */
    public function getPostArray()
    {
        return $this->filteredPostVar;
    }

    /**
     * 获取所有get参数
     *
     * @return array
     */
    public function getGetArray()
    {
        return $this->filteredGetVar;
    }

    public function getPost($varName, $default = null)
    {
        return isset($this->filteredPostVar[$varName]) ? $this->filteredPostVar[$varName] : $default;

    }

    public function getGet($varName, $default = null)
    {
        return isset($this->filteredGetVar[$varName]) ? $this->filteredGetVar[$varName] : $default;

    }

    /**
     * 获取原始未解析的HTTP BODY数据，如：二进制数据、XML、JSON...
     *
     * @return string
     */
    public function getRawBody()
    {
        return file_get_contents('php://input');
    }

    public function getServer($varName, $default = null)
    {
        $varName = strtoupper($varName);
        return isset($this->filteredServerVar[$varName]) ? $this->filteredServerVar[$varName] : $default;

    }

    public function isPost()
    {
        return $this->isRequestMethod(self::HTTP_REQUEST_METHOD_POST);
    }

    public function isGet()
    {
        return $this->isRequestMethod(self::HTTP_REQUEST_METHOD_GET);
    }

    public function isPut()
    {
        return $this->isRequestMethod(self::HTTP_REQUEST_METHOD_PUT);
    }

    public function isRequestMethod($method)
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == $method;
    }

    /**
     * 利用$_SERVER解析请求头部信息
     *
     * @return array
     */
    protected function parseRequestAllHeadersByServer()
    {
        $headers = array();
        if (!$this->isCli()) {
            foreach ($_SERVER as $key => $val) {
                if (!strncasecmp($key, 'HTTP', 4)) {
                    $array = explode('_', $key);
                    array_shift($array);
                    $headers[strtolower(join('-', $array))] = $val;
                }
            }
        }
        return $headers;
    }

    public function getRequestHeader($key)
    {
        $key = strtolower($key);
        return isset($this->requestHeaders[$key]) ? $this->requestHeaders[$key] : null;
    }

    public function isRequestJson()
    {
        return !strncasecmp($this->getRequestHeader('content-type'), 'application/json', 16);
    }

    /**
     * 判断当前是否是AJAX请求
     *
     * @return bool
     */
    public function isXhr()
    {
        return $this->getRequestHeader('x-requested-with') == 'xmlhttprequest';
    }

    /**
     * 根据当前请求参数与密钥生成参数签名
     *
     * @param string $key 密钥
     * @param array $exclude 需要排除的参数名称
     * @return string
     */
    public function urlHashCode($key, array $exclude = array())
    {
        $code = '';
        $params = array();
        if ($this->isPost()) {
            $params = $this->getOrigGpcssArray(self::HTTP_REQUEST_VAR_POST);
        } elseif ($this->isGet()) {
            $params = $this->getOrigGpcssArray(self::HTTP_REQUEST_VAR_GET);
        }
        if ($params) {
            $code = OtherHelper::urlMd5HashCode($params, $key, $exclude);
        }
        return $code;
    }


    /**
     * 判断当前是否是以命令行模式运行
     *
     * @return bool
     */
    public function isCli()
    {
        return isPhpCommandMode();
    }

    /**
     * 判断客户端是否指定需要JSON格式响应，必须通过指定
     * HTTP请求头Accept: application/json
     *
     * @return bool
     */
    public function isNeedJson()
    {
        return !strncasecmp($this->getRequestHeader('accept'), 'application/json', 16);
    }
}