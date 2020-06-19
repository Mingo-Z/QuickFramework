<?php
use Qf\Kernel\ComponentManager;
use Qf\Kernel\Application;

/**
 * 获取环境配置参数
 *
 * @param string $key 配置键名
 * @param string $section 节点名称
 * @param null $default 默认值
 * @return mixed|null
 */
function envIniConfig($key, $section = 'global', $default = null)
{
    $ret = $default;
    $envIniFile = AppPath . '.env';
    if (is_file($envIniFile)) {
        $entries = parse_ini_file($envIniFile, true);
        if ($entries && isset($entries[$section]) && isset($entries[$section][$key])) {
            $ret = $entries[$section][$key];
        }
    }

    return $ret;
}

function getTheProbabilityValue(array $initArgs, $base = 100){
    static $pValues = array();

    $key = md5(json_encode($initArgs));
    if (!isset($pValues[$key])) {
        $totalValue = array_sum($initArgs);
        foreach ($initArgs as $elem => $pVal) {
            $pVal = intval($pVal / $totalValue * $base);
            for ($i = 0; $i < $pVal; $i++) {
                $pValues[$key][] = $elem;
            }
        }
    }
    return $pValues[$key][mt_rand(0,count($pValues[$key])-1)];
}

function getFilePartContents($file, $n, $seek = SEEK_SET)
{
    $ret = false;
    if (is_file($file) && ($fileHandle = fopen($file, 'rb'))) {
        fseek($fileHandle, $seek);
        $ret = fread($fileHandle, (int)$n);
        fclose($fileHandle);
    }

    return $ret;
}

function getHttpClientIp()
{
    $clientIp = null;
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $clientIp = $ips[0];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $clientIp = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $clientIp = $_SERVER['REMOTE_ADDR'];
    }

    return $clientIp;
}

function isPhpLoadedExtension($name)
{
    return extension_loaded($name);
}

function networkHttpMultiFileDownload(array $urlToLocalFiles, $concurrentNum = 60, callable $contentCallBack = null)
{
    $files = [];
    $curlHandles = [];
    $mh = curl_multi_init();
    $isFullCompleted = false;
    $totalNum = count($urlToLocalFiles);
    foreach ($urlToLocalFiles as $url => $localFile) {
        $files[$url] = [
            'url' => $url,
            'localFilePath' => $localFile,
            'isCompleted' => false,
            'error' => '',
        ];
        $curlHandle = curl_init($url);
//        $fileHandle = fopen($localFile, 'wb');
        $options = [
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT =>720,
//            CURLOPT_FILE => $fileHandle,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
            CURLOPT_FOLLOWLOCATION => true,
        ];
        if (stripos($url, 'https') === 0) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }
        curl_setopt_array($curlHandle, $options);
        curl_multi_add_handle($mh, $curlHandle);
        $curlHandles[$url] = $curlHandle;
        $cntCurlHandlesNum = count($curlHandles);
        if ($cntCurlHandlesNum == $concurrentNum || $cntCurlHandlesNum == $totalNum ) {
            $totalNum -= $cntCurlHandlesNum;
            $running = 0;
            do {
                $mrc = curl_multi_exec($mh,$running);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

            while ($running > 0 && $mrc == CURLM_OK) {
                do {
                    $mrc = curl_multi_exec($mh, $running);
                } while ($mrc == CURLM_CALL_MULTI_PERFORM);

                if ($mrc > 0) { // error
                    trigger_error(curl_multi_strerror($mrc), E_USER_ERROR);
                    break;
                }

                if (curl_multi_select($mh, 1.1) == -1) {
                    usleep(1000);
                }
            }

            foreach ($curlHandles as $url => $curlHandle) {
                $info = curl_getinfo($curlHandle);
                if (($error = curl_error($curlHandle))) {
                    $files[$url]['error'] = $error;
                    trigger_error($error, E_USER_ERROR);
                } elseif ($info['http_code'] == 200) {
                    $content = curl_multi_getcontent($curlHandle);
                    if ($content && (!$contentCallBack || $contentCallBack($content, $url))) {
                        $fileHandle = fopen($files[$url]['localFilePath'], 'wb');
                        if ($fileHandle) {
                            fwrite($fileHandle, $content);
                            fclose($fileHandle);
                            $files[$url]['isCompleted'] = true;
                        }
                    } else {
                        trigger_error("fetch $url content is empty or format error", E_USER_ERROR);
                    }
                }
                curl_multi_remove_handle($mh, $curlHandle);
                curl_close($curlHandle);
            }

            $curlHandles = [];
        }
    }
    curl_multi_close($mh);
    foreach ($files as $file) {
        if (!$file['isCompleted']) {
            $isFullCompleted = false;
            break;
        }
    }
    return [$isFullCompleted, $files];
}

function getNowTimestampMs()
{
    return (int)(microtime(true) * 1000);
}


function networkHttpBigFileDownload($url, $storageFullFilePath)
{
    $origFileDataLen = 0;
    $downloadedFileDataLen = 0;
    $urlComponents = parse_url($url);
    if ($urlComponents) {
        $urlComponents = array_merge([
            'port' => 80,
            'path' => '/',
            'query' => '',
        ], $urlComponents);
        $errno = 0;
        $errstr = '';
        $sock = fsockopen($urlComponents['host'], $urlComponents['port'], $errno, $errstr, 3);
        if ($sock) {
            $requestBody =<<<EOT
GET {$urlComponents['path']}?{$urlComponents['query']} HTTP/1.1
Host: {$urlComponents['host']}
User-Agent: network
Referer: {$urlComponents['scheme']}://{$urlComponents['host']}
ConnectionTrait: close

        
EOT;
            if (fwrite($sock, $requestBody)) {
                while (($line = fgets($sock, 1024))) {
                    if (stripos($line, "content-length") !== false) {
                        $lines = explode(':', $line);
                        $lines = array_map('trim', $lines);
                        $origFileDataLen = $lines[1];
                    }
                    if ($line == "\r\n") {
                        break;
                    }
                }

                if ($origFileDataLen > 0 && ($fileHandle = fopen($storageFullFilePath, 'ab'))) {
                    while (($buffer = fread($sock, 1024))) {
                        $n = fwrite($fileHandle, $buffer);
                        $downloadedFileDataLen += $n;
                    }
                    fclose($fileHandle);
                }
            }

            fclose($sock);
        }
    }

    return [
        'origFileDataLen' => $origFileDataLen,
        'downloadedFileDataLen' => $downloadedFileDataLen,
        'isCompleted' => ($origFileDataLen == $downloadedFileDataLen),
    ];
}

/**
 * 判断是否是局域网IP
 *
 * @param  string $ip xxx.xxx.xxx.xxx
 * @return bool
 */
function isLanIP($ip)
{
    $ret = false;
    $scope = array(
        'ip127' => array(2130706433, 2130706433), // 127.0.0.1
        'ip10' => array(167772160, 184549375), // 10.0.0.0-10.255.255.255
        'ip172' => array(2886729728, 2887778303), // 172.16.0.0-172.31.255.255
        'ip192' => array(3232235520, 3232301055) // 192.168.0.0-192.168.25.255
    );
    $ipArray = explode('.', $ip);
    $scopeKey = 'ip' . $ipArray[0];
    if (isset($scope[$scopeKey])) {
        $longIp = ip2long($ip);
        if ($longIp >= $scope[$scopeKey][0] && $longIp <= $scope[$scopeKey][1]) {
            $ret = true;
        }
    }
    return $ret;
}

/**
 * 转为Y-m-d H:i:s日期时间为Y-m-d\TH:i:s\Z
 *主要用于solr日期时间范围查询
 *
 * @param $datetime Y-m-d H:i:s日期时间
 * @param bool $isGm 是否是格林威治时间
 * @return string
 */
function toGmTzDatetime($datetime, $isGm = true)
{
    $offsetTime = date('Z'); // 当前时区设置与UTC相差的秒数
    $timestamp = strtotime($datetime);
    if ($timestamp && $isGm) {
        $timestamp += $offsetTime;
    }
    return gmdate('Y-m-d\TH:i:s\Z', ($timestamp ? $timestamp : time()));
}

/**
 *
 * 写日志
 *
 * @param mixed $msg 日志内容
 * @param string $logFile 日志文件名
 * @return bool|int
 */
function writeLog($msg, $logFile = 'logs.log')
{
    $ret = false;
    if ($msg && $logFile && !preg_match('@php$@', $logFile)) {
        $logDirPath = defined('LogDirPath') ? LogDirPath : 'logs/';
        if (!is_dir($logDirPath)) {
            mkdir($logDirPath, 0755);
        }
        $logFilePath = $logDirPath . $logFile;
        if (!is_scalar($msg)) {
            $msg = json_encode($msg);
        }
        $logMsg = sprintf("[%s]\t%s\n", date('r'), $msg);
        $ret = file_put_contents($logFilePath, $logMsg, FILE_APPEND);
    }
    return $ret;
}

/**
 * 加载指定名称配置的组件，必须依赖路径定义及组件配置文件
 * 
 * @param string $name
 * @return mixed object|null
 */
function getComponent($name)
{
    static $objects = array();
    if (!isset($objects[$name]) && ($com = getCom())) {
        $objects[$name] = $com->$name;
    }
    return isset($objects[$name]) ? $objects[$name] : null;
}

/**
 * 获取组件加载器
 *
 * @return ComponentManager
 */
function getCom()
{
    static $com;
    if (!$com && !($com = Application::getCom())) {
        $configFilePath = FrameworkConfigsPath . 'components.config.php';
        if (is_file($configFilePath)) {
            $com = new ComponentManager();
            $com->configFile = $configFilePath;
        }
    }
    return $com;
}

/**
 * 判断PHP脚本是否已命令行模式运行，主要用于
 * 后台任务处理，如：cron
 * 
 * @return bool
 */
function isPhpCommandMode()
{
    return in_array(PHP_SAPI, array('cli'));
}

/**
 * 
 * $rule = array(
 * 'limit' => array(0, 20),
 * 'order' => array('desc' => array('created'), 'asc' => array('updated')),
 * 'query' => "需要OR查询的SQL条件,与solr类似"
 * 'conds' => array(
 * 'eq' => array('sign' => 1),
 * 'in' => array('sex' => array(1, 2)),
 * 'scope' => array('id' => array('min' => 0, 'max' => 1000)),
 * 'like' => array('name' => 'demo')
 * )
 * )
 * 
 * @param array $rule MySQL查询条件定义数组
 * @return array 包含查询条件where、排序order、查询数量limit
 */
function parseConds(array $rule)
{   
    $result = array(
        'where' => '1',
        'order' => '',
        'limit' => ''
    );

    $opFormats = array(
        'ge' => "%s >= '%d'",
        'le' => "%s <= '%d'",
        'gt' => "%s > '%d'",
        'lt' => "%s < '%d",
        'eq' => "%s = '%s'",
        'neq' => "%s != '%s'",
        'in' => "%s IN ('%s')",
        'nin' => "%s NOT IN ('%s')",
        'scope' => "(%s BETWEEN '%s' AND '%s')",
        'like' => "%s LIKE '%s'"
    );
    
    if (isset($rule['conds']) && is_array($rule['conds'])) {
        foreach ($rule['conds'] as $op => $define) {
            $format = isset($opFormats[$op]) ? $opFormats[$op] : '';
            if ($format) {
                $logicOp = 'AND';
                $conds = array();
                foreach ($define as $key => $value) {
                    $key = "`$key`";
                    if (in_array($op, array('eq', 'neq', 'like', 'ge', 'le', 'gt', 'lt'))) {
                        if ($op == 'like') {
                            $logicOp = 'OR';
                            $likeMode = 0;
                            if (is_array($value)) {
                                isset($value['mode']) && $likeMode = $value['mode'];
                                $value = $value['kw'];
                            }
                            $prefix = $likeMode == 2 ? '%' : ($likeMode == 0 ? '%' : '');
                            $suffix = $likeMode == 1 ? '%' : ($likeMode == 0 ? '%' : '');
                            $value = "$prefix$value$suffix";
                        }
                        $conds[] = sprintf($format, $key, $value);
                    } elseif (in_array($op, array('in', 'nin'))) {
                        $value = is_array($value) ? $value : array($value);
                        $conds[] = sprintf($format, $key, join("', '", $value));
                    } elseif ($op == 'scope' && is_array($value) 
                        && isset($value['min']) && isset($value['max'])) {
                        $conds[] = sprintf($format, $key, $value['min'], $value['max']);
                    }
                }
                if(!empty($conds)){
                    $result['where'] .= ' AND (' . join(" $logicOp ", $conds) . ')';
                }
            }

        }
    }
    if (isset($rule['query'])) {
        $result['where'] .=  ' AND (' . $rule['query'] . ')';
    }
    if (isset($rule['limit']) && is_array($rule['limit']) 
        && count($rule['limit']) == 2) {
        $result['limit'] = 'LIMIT ' . $rule['limit'][0] . ', ' . $rule['limit'][1];
    }
    $orders = array();
    if (isset($rule['order']) && is_array($rule['order'])) {
        foreach ($rule['order'] as $order => $keys) {
            foreach ($keys as $key) {
                $orders[] = "`$key` $order";
            }
        }
        $result['order'] = 'ORDER BY ' . join(', ', $orders);
    }
    return $result;
}

/**
 *
 * URL参数安全base64，替换其中的‘+’、‘/’、‘=’对于URL的特殊字符
 *
 * @author fengxu
 * @param string $string
 * @param string $operation ENCODE|DECODE
 * @return string
 */
function urlParamBase64Code($string, $operation = 'ENCODE')
{
    $searchKws = array('+', '/', '=');
    $replaceKws = array('_', '-', '');
    $ret = '';
    if ($operation == 'DECODE') {
        $ret = base64_decode(str_replace($replaceKws, $searchKws, $string));
    } else {
        $ret = str_replace($searchKws, $replaceKws, base64_encode($string));
    }
    return $ret;
}

/**
 * 判断当前web服务器是已启用HTTPS
 *
 * @return bool
 */
function isHttps()
{
    $ret = false;
    if ((isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (isset($_SERVER['HTTPS']) && !strcasecmp($_SERVER['HTTPS'], 'on'))) {
        $ret = true;
    }

    return $ret;
}

/**
 * json encode不unicode编码多字节字符
 *
 * @param mixed $value
 * @return mixed|string
 */
function jsonUnescapeUnicodeEncode($value)
{
    if (defined('JSON_UNESCAPED_UNICODE')) { // >= PHP 5.4.0
        $ret = json_encode($value, JSON_UNESCAPED_UNICODE);
    } else {
        $ret = json_encode($value);
        if ($ret) {
            $ret = preg_replace_callback('@\\\\u(\w{4})@', function ($matches) {
                return html_entity_decode('&#x' . $matches[1] . ';', ENT_COMPAT, 'UTF-8');
            }, $ret);
        }
    }
    return $ret;
}

function addCsrfToken($salt = null, $expire = 300)
{
    return '<input type="hidden" name="csrf_token" value="' . \Qf\Components\CsrfProvider::getToken($salt, $expire) . '" />';
}

function translate()
{
    return Application::getLocale()->translate(...func_get_args());
}

function isDebug()
{
    return envIniConfig('appIsDebug', 'global', true);
}





