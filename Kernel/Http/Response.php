<?php
namespace Qf\Kernel\Http;

use Qf\Components\Facades\Cookie;

class Response
{
    protected $version;

    protected static $statusCodeToTexts = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',            // RFC2518
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',          // RFC4918
        208 => 'Already Reported',      // RFC5842
        226 => 'IM Used',               // RFC3229
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',    // RFC7238
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Payload Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',                                               // RFC2324
        421 => 'Misdirected Request',                                         // RFC7540
        422 => 'Unprocessable Entity',                                        // RFC4918
        423 => 'Locked',                                                      // RFC4918
        424 => 'Failed Dependency',                                           // RFC4918
        425 => 'Reserved for WebDAV advanced collections expired proposal',   // RFC2817
        426 => 'Upgrade Required',                                            // RFC2817
        428 => 'Precondition Required',                                       // RFC6585
        429 => 'Too Many Requests',                                           // RFC6585
        431 => 'Request Header Fields Too Large',                             // RFC6585
        451 => 'Unavailable For Legal Reasons',                               // RFC7725
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates (Experimental)',                      // RFC2295
        507 => 'Insufficient Storage',                                        // RFC4918
        508 => 'Loop Detected',                                               // RFC5842
        510 => 'Not Extended',                                                // RFC2774
        511 => 'Network Authentication Required',                             // RFC6585
    );

    /**
     * 内容格式
     *
     * @var string
     */
    protected $contentType;
    /**
     * HTTP状态码
     *
     * @var int
     */
    protected $_statusCode;

    /**
     * HTTP响应状态描述
     *
     * @var string
     */
    protected $_statusText;

    /**
     * 响应内容
     *
     * @var mixed
     */
    protected $content;

    /**
     * 响应内容是否json编码
     *
     * @var bool
     */
    protected $isEncodeContentJson = false;

    /**
     * 响应头部
     *
     * @var array
     */
    protected $_headers = array();

    /**
     * @var Request
     */
    protected $request;

    /**
     * 是否已经设置响应实例
     *
     * @var bool
     */
    protected $_processed;

    public function __construct(Request $request)
    {
        $this->charset = 'utf-8';
        $this->contentType = 'text/html';
        $this->_statusCode = 200;
        $this->_statusText = self::$statusCodeToTexts[$this->_statusCode];
        $this->version = '1.1';
        $this->_processed = false;
        $this->request = $request;
    }

    public function setCookie(
        $name,
        $value = '',
        $expire = 0,
        $httpOnly = true
    )
    {
        $ret = false;
        if (!headers_sent()) {
            $ret = Cookie::set($name, $value, $expire, $httpOnly);
        }
        return $ret;
    }
    /**
     * 动态设置cors跨域请求配置
     *
     * @param array|null $domains 允许请求的域名，默认不限制
     * @param array|null $methods 允许请求的方式，GET、POST、OPTIONS、PUT、DELETE、HEAD等，默认不限制
     * @param array|null $headers 允许请求的头，默认不限制
     * @param int $maxAge 在该时间内不需要再次进行预请求检查
     * @return $this
     */
    public function setAllowCrossDomains(array $domains = null, array $methods = null, array $headers = null, $maxAge = 86400)
    {
        $domains = $domains ? join(', ', $domains) : '*';
        $methods = $methods ? join(', ', $methods) : '*';
        $headers = $headers ? join(', ', $headers) : '*';
        
        $this->setHeader('Access-Control-Allow-Origin', $domains);
        if ($this->request->isRequestMethod('OPTIONS')) {
            $this->setHeader('Access-Control-Allow-Headers', $headers);
            $this->setHeader('Access-Control-Allow-Methods', $methods);
            $this->setHeader('Access-Control-Max-Age', (int)$maxAge);
        }

        return $this;
    }

    /**
     * 根据.env配置设置cors跨域请求头部
     *
     * @return $this
     */
    protected function setCorsHeaders()
    {
        if ($this->request->isCors() && ($corsAllowDomains = envIniConfig('corsAllowDomains', 'http'))) {
            $corsAccessMaxAge = envIniConfig('corsAccessMaxAge', 'http', 86400);
            $this->setAllowCrossDomains(explode(',', $corsAllowDomains), null, null, $corsAccessMaxAge);
        }

        return $this;
    }

    public function setEtag($etag)
    {
        $this->setHeader('ETag', $etag);
        return $this;
    }

    /**
     * 设置当前内容修改时间
     *
     * @param int $timestamp
     * @return $this
     */
    public function setLastModified($timestamp = null)
    {
        $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT', $timestamp);
        return $this;
    }

    public function setVersion($version = '1.1')
    {
        $this->version = $version;
        return $this;
    }

    public function setHeader($key, $value)
    {
        $this->_headers[$key] = $value;
        return $this;
    }

    public function setCharset($encoding = 'utf-8')
    {
        $this->charset = $encoding;
        return $this;
    }

    public function setContentType($type = 'text/html')
    {
        $this->contentType = $type;
        return $this;
    }

    /**
     * 设置响应内容
     *
     * @param mixed $content
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * 设置json格式响应内容不能与setContent、toJson同时使用
     *
     * @param mixed $var 响应内容
     * @return $this
     */
    public function setJsonContent($var)
    {
        $this->setContent($var);
        $this->isEncodeContentJson = true;
        $this->setContentType('application/json');

        return $this;
    }

    /**
     * 设置当前响应实例是否可用
     *
     * @param bool $isEnable
     * @return $this
     */
    public function setProcessed($isEnable = true)
    {
        $this->_processed = $isEnable;
        return $this;
    }

    public function setCode($statusCode = 200)
    {
        $statusCode = (int)$statusCode;
        if (isset(self::$statusCodeToTexts[$statusCode])) {
            $this->_statusCode = $statusCode;
            $this->_statusText = self::$statusCodeToTexts[$statusCode];
        }
        return $this;
    }

    public function redirect($url, array $params = array(), $isImmediate = false, $isTempRedirect = true)
    {
        if (!$this->request->isXhr()) {
            $this->setCode($isTempRedirect ? 302 : 301);
            if ($params) {
                $separator = (strpos($url, '?') !== false) ? '&' : '?';
                $url .= $separator . http_build_query($params);
            }
            $this->setHeader('Location', $url);
            if ($isImmediate) {
                $this->_sendHeaders();
                die();
            }
        }
        return $this;
    }

    protected function _sendHeaders()
    {
        if (!headers_sent()) {
            $this->setCorsHeaders();
            header("HTTP/{$this->version} {$this->_statusCode} {$this->_statusText}", false, $this->_statusCode);
            $this->setHeader('Content-Type', $this->contentType . '; charset=' . $this->charset);
            foreach ($this->_headers as $key => $value) {
                header("$key: $value", true);
            }
        }
    }
    protected function _isCanContent()
    {
        $isCan = true;
        switch ($this->_statusCode) {
            case $this->_statusCode >= 300 && $this->_statusCode <= 307:
            case $this->_statusCode >= 100 && $this->_statusCode <= 102:
                $isCan = false;
                break;
            default:
                $isCan = true;
        }
        return $isCan;
    }

    protected function _sendContent()
    {
        echo $this->isEncodeContentJson ? json_encode($this->content) : $this->content;
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    /**
     * 终止执行后面的逻辑
     */
    public function stop()
    {
        die();
    }

    public function send()
    {
        if ($this->isProcessed()) {
            // 清除未通过return，直接输出到缓存冲区的内容
            if (ob_get_level()) {
                ob_clean();
            }
            // 针对请求要求JSON内容响应的自动处理
            if ($this->request->isNeedJson() && !$this->isEncodeContentJson) {
                $this->setJsonContent($this->content);
            }
            $this->_sendHeaders();
            if ($this->_isCanContent()) {
                $this->_sendContent();
            }
        }
        return $this;
    }

    public function isProcessed()
    {
        return $this->_processed;
    }

    /**
     * HTTP OPTIONS请求处理
     */
    public function options()
    {
        if ($this->request->isRequestMethod('OPTIONS')) {
            // 返回空内容中断不执行后面的逻辑
            $this->setProcessed(true)->send()->stop();
        }
    }

    /**
     * 获取PHP缓存区已有的内容，并且清空
     *
     * @return string
     */
    public function getBufferContent()
    {
        $content = '';
        // 只处理一层缓冲区的内容
        if (ob_get_level()) {
            $content = ob_get_clean();
        }
        return $content;
    }

    public function getContent()
    {
        return $this->content;
    }
}
