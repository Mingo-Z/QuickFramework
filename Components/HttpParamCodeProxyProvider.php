<?php
namespace Qf\Components;

use Qf\Utils\OtherHelper;
use Qf\Utils\HttpClient;

/**
 * 支持参数MD5合法性签名的HTTP请求封装
 *
 * @version $Id: $
 */

class HttpParamCodeProxyProvider extends Provider
{
    const HTTP_REQUEST_USER_AGENT = 'HttpParamCodeProxy';
    const HTTP_REQUEST_METHOD_POST = 'POST';
    const HTTP_REQUEST_METHOD_GET = 'GET';

    /**
     * 参数签名密钥
     *
     * @var string
     */
    public $accessKey;
    /**
     * 是否启用参数签名
     *
     * @var bool
     */
    public $enableParamCode;
    public $paramCodeName;

    public $timeout;
    /**
     * http or https
     *
     * @var string
     */
    public $scheme;
    /**
     * 主机域名或IP
     *
     * @var string
     */
    public $host;
    public $port;
    public $path;
    public $params;
    /**
     * 公共参数
     *
     * @var array
     */
    public $commonParams;
    public $httpProxy;

    /**
     * 是否只接收JSON格式的响应数据
     *
     * @var bool
     */
    protected $isAcceptJson;
    protected $requestMethod;
    protected $headers;

    public function __construct()
    {
        $this->accessKey = '';
        $this->enableParamCode = false;
        $this->paramCodeName = 'signCode';
        $this->scheme = 'http';
        $this->port = 80;
        $this->path = '/';
        $this->timeout = 5;
        $this->isAcceptJson = false;
        $this->params = [];
        $this->commonParams = [];
        $this->headers = [];
    }

    /**
     * 设置请求URL
     *
     * @param $url
     * @return $this
     */
    public function setRequestUrl($url)
    {
        $this->params = [];
        $this->headers = [];

        if (($urlComponents = parse_url($url))) {
            $this->scheme = isset($urlComponents['scheme']) ? strtolower($urlComponents['scheme']) : 'http';
            $this->host = strtolower($urlComponents['host']);
            $this->port = isset($urlComponents['port']) ? $urlComponents['port'] : (
                $this->isHttps() ? 443 : 80
            );
            $this->path = isset($urlComponents['path']) ? $urlComponents['path'] : '/';
            if (isset($urlComponents['query'])) {
                parse_str($urlComponents['query'], $this->params);
            }
        }


        return $this;
    }

    public function acceptJson()
    {
        $this->isAcceptJson = true;
        $this->setHeader('accept', 'application/json');
        
        return $this;
    }

    /**
     * 设置HTTP请求头部信息，必须在request执行之前调用，否则无效
     *
     * @param string $key 头部字段名称
     * @param string $value 头部字段值
     * @return $this
     */
    public function setHeader($key, $value)
    {
        $this->headers[strtolower($key)] = $value;
        return $this;
    }

    public function request($method = HttpParamCodeProxyProvider::HTTP_REQUEST_METHOD_GET, array $params = null, $timeout = null)
    {
        $responseBody = null;

        if ($this->commonParams) {
            $this->params = array_merge($this->commonParams, $this->params);
        }
        if ($params) {
            $this->params = array_merge($params, $this->params);
        }
        if ($this->enableParamCode) {
            $this->setParamCode();
        }

        if ($timeout !== null) {
            $this->timeout = (int)$timeout;
        }
        $this->requestMethod = $method;
        if ($this->isCanHttp()) {
            $url = $this->scheme . "://" . $this->host . ':' . $this->port . $this->path;
            $params = $this->params;
            if (!$this->isPost()) {
                $url .= '?' . http_build_query($this->params);
                $params = null;
            }
            $responseBody = HttpClient::request($url, $method, $params, $this->getHttpHeaders(),
                $this->timeout, $this->httpProxy);
            if ($responseBody && $this->isAcceptJson) {
                $responseBody = json_decode($responseBody, true);
            }
        }

        return $responseBody;
    }

    /**
     * 获取设置的HTTP headers
     *
     * @return array
     */
    protected function getHttpHeaders()
    {
        if (!isset($this->headers['user-agent'])) {
            $this->headers['user-agent'] = self::HTTP_REQUEST_USER_AGENT;
        }
        if ($this->isPost() && !isset($this->headers['content-type'])) {
            $this->headers['content-type'] = 'application/x-www-form-urlencoded';
        }

        return $this->headers;
    }

    public function isPost()
    {
        return !strcasecmp($this->requestMethod, 'POST');
    }

    public function isHttps()
    {
        return !strcasecmp($this->scheme, 'https');
    }

    /**
     * 用accessKey生成参数合法性MD5签名
     *
     * @return void
     */
    protected function setParamCode()
    {
        if ($this->params) {
            $this->params[$this->paramCodeName] = OtherHelper::urlMd5HashCode($this->params, $this->accessKey);
        }
    }

    protected function isCanHttp()
    {
        $ret = true;

        if (!$this->host) {
            trigger_error(__CLASS__ . ' host parameter can not be empty', E_USER_WARNING);
            $ret = false;
        } elseif ($this->isPost() && !$this->params) {
            trigger_error(__CLASS__ . ' POST method request params can not be empty', E_USER_WARNING);
            $ret = false;
        }

        return $ret;
    }

}

