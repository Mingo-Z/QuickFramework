<?php
namespace Qf\Utils;

class HttpClient
{
    const REQUEST_METHOD_POST = 'POST';
    const REQUEST_METHOD_GET = 'GET';

    /**
     * POST文件上传参数构造
     *
     * @example
     *
     * $postFields = [
     *      'key0' => 'value0',
     *      'key1' => 'value1',
     *      ...
     * ];
     * $fileParamKv = HttpClient::makeFile('mp3', '/test/test.mp3');
     * $postFields += $fileParamKv;
     * $fileParamKv = HttpClient::makeFile('mp4', '/test/test.mp4');
     * $postFields += $fileParamKv;
     * HttpClient::request('http://dest', HttpClient::REQUEST_METHOD_POST, $postFields);
     *
     * @param string $varName POST参数键名，必须唯一
     * @param string $file 上传的文件
     * @param string|null $uploadName 指定上传的文件名
     * @param string|null $mimeType 文件类型
     * @return array
     */
    public static function makeFile($varName, $file, $uploadName = null, $mimeType = null)
    {
        $paramKv = [];
        if ($varName && is_file($file)) {
            if ($uploadName === null) {
                $uploadName = basename($file);
            }
            if ($mimeType === null && ($decMimeType = mime_content_type($file))) {
                $mimeType = $decMimeType;
            }
            $paramKv[$varName] = new \CURLFile($file, $mimeType, $uploadName);
        }

        return $paramKv;
    }

    /**
     * @param string $url
     * @param string $method
     * @param null|string|array $params
     * @param array|null $headers
     * @param int $timeout
     * @param null|string $proxy   protocol://username:password@host:port, http://test:123@127.0.0.1:8080
     * socks5://test:123@127.0.0.1:8080
     * @param bool $isReturnResponseHeader if true, return [responseBody, responseHeader]
     * @return bool|string|array
     */
    public static function request($url, $method = HttpClient::REQUEST_METHOD_POST,
                                   $params = null, array $headers = null, $timeout = 30,
                                   $proxy = null, $isReturnResponseHeader = false)
    {
        $curlHandle = curl_init($url);
        curl_setopt($curlHandle, CURLOPT_TIMEOUT, (int)$timeout);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HEADER, false);

        if ($headers) {
            $httpHeaders = [];
            foreach ($headers as $key => $value) {
                $httpHeaders[] = "$key: $value";
            }
            curl_setopt($curlHandle, CURLOPT_HTTPHEADER, $httpHeaders);
        }

        if ($method == self::REQUEST_METHOD_POST) {
            curl_setopt($curlHandle, CURLOPT_POST, true);
            curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $params); // If $params is an array, the Content-Type header will be set to multipart/form-data
        }
        if (!strncasecmp($url, 'https', 5)) {
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, 0);
        }
        if ($proxy) { // protocol://username:password@host:port
            if (!strncasecmp('socks5://', $proxy, 9)) {
                $proxyType = CURLPROXY_SOCKS5;
            } else {
                $proxyType = CURLPROXY_HTTP;
            }
            if (($pos = strrpos($proxy, '/')) !== false) {
                $proxy = substr($proxy, $pos + 1);
            }
            list($proxyAuth, $proxyServer) = explode('@', $proxy);
            if ($proxyServer) {
                curl_setopt($curlHandle, CURLOPT_PROXYTYPE, $proxyType);
                curl_setopt($curlHandle, CURLOPT_PROXY, $proxyServer);
                if ($proxyAuth) {
                    curl_setopt($curlHandle, CURLOPT_PROXYUSERPWD, $proxyAuth);
                }
            }
        }
        $responseHeader = null;
        if ($isReturnResponseHeader) {
            curl_setopt($curlHandle, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$responseHeader) {
                $responseHeader .= $header;
                return strlen($header);
            });
        }

        $responseBody = curl_exec($curlHandle);
        if (($errno = curl_errno($curlHandle))) {
            trigger_error(curl_error($curlHandle) . "($errno)", E_USER_ERROR);
        }
        curl_close($curlHandle);

        return ($isReturnResponseHeader && $responseBody) ? [$responseBody, $responseHeader] : $responseBody;
    }

}