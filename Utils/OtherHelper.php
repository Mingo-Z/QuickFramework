<?php
namespace Qf\Utils;

class OtherHelper
{

    /**
     *距离现在多少时间
     *
     * @param int $agoTimestamp
     * @return array|null
     */
    public static function getHowTimeAgo($agoTimestamp)
    {
        $timeDesc = null;

        $subValue = time() - $agoTimestamp;
        $units = [
            'month' => 86400 * 30,
            'week' => 86400 * 7,
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1,
        ];

        if ($subValue > 0) {
            foreach ($units as $unit => $value) {
                if ($subValue >= $value) {
                    $timeDesc = [
                        'num' => (int)($subValue / $value),
                        'unit' => $unit,
                    ];
                    break;
                }
            }
        }

        return $timeDesc;
    }

    public static function createGuid()
    {
        $md5Str =md5(microtime(true) . uniqid(true));
        return sprintf("%s-%s-%s-%s-%s",
            substr($md5Str, 0, 8), substr($md5Str, 8, 4), substr($md5Str, 12, 4)
            , substr($md5Str, 16, 4) , substr($md5Str, 20));
    }

    /**
     * 大小单位转换
     *
     * @param int $bytes
     * @param int $precision 保留精度
     * @return bool|array
     */
    public static function sizeHuman($bytes, $precision = 2)
    {
        $sieDesc = false;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        if ($bytes > 0) {
            $i = 5;
            while ($i >= 0) {
                if ($bytes >= ($unitSize = pow(1024, $i))) {
                    $sieDesc['num'] = round($bytes / $unitSize, $precision);
                    $sieDesc['unit'] = $units[$i];
                    break;
                }
                $i--;
            }
        }

        return $sieDesc;
    }

    /**
     * url参数签名
     *
     * @param array $array 参数数组
     * @param string $hashKey 签名key
     * @param array $excludeFields 排除字段
     * @param string $sep 键值分隔符
     * @param string $algo 内部hash算法
     * @return bool|string
     */
    public static function urlMd5HashCode(array $array, $hashKey,
                                          array $excludeFields = null, $sep = '=', $algo = 'sha256')
    {
        $code = false;

        if ($array) {
            ksort($array);
            $tmpArray = [];
            foreach ($array as $key => $value) {
                if (!$excludeFields || !in_array($key, $excludeFields)) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $tmpArray[] = "$key$sep$value";
                }
            }
            if ($tmpArray) {
                $code = md5(hash($algo, join('&', $tmpArray)) . $hashKey);
            }
        }

        return $code;
    }

    /**
     * RSA签名
     *
     * @param array $hashFields 参数数组
     * @param $privateKey rsa私钥
     * @param array|null $excludeFields 排出字段
     * @param string $sep 键值分隔符
     * @param string $algo openssl签名算法
     * @return bool|string
     */
    public static function urlRsaHashCode(array $hashFields, $privateKey,
                                          array $excludeFields = null, $sep = '=', $algo = OPENSSL_ALGO_SHA1)
    {
        $code = false;
        if ($hashFields) {
            ksort($hashFields);
            $tmpArray = [];
            foreach ($hashFields as $key => $value) {
                if (!$excludeFields || !in_array($key, $excludeFields)) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    $tmpArray[] = "$key$sep$value";
                }
            }
            if ($tmpArray && ($pkeyId = openssl_pkey_get_private($privateKey))) {
                openssl_sign(join('&', $tmpArray), $code, $pkeyId, $algo);
                openssl_free_key($pkeyId);
            }
        }

        return $code;
    }

    public static function urlSafeBase64Encode($data)
    {
        $ret = '';
        if ($data) {
            $ret = base64_encode($data);
            $ret = str_replace(['+', '/', '='], ['_', '-', ''], $ret);
        }

        return $ret;
    }

    public static function urlSafeBase64Decode($data)
    {
        $ret = '';
        if ($data) {
            $ret = str_replace(['_', '-'], ['+', '/'], $data);
            $ret = base64_decode($ret);
        }

        return $ret;
    }

    public static function randomAlnum($length = 8)
    {
        $ret = '';

        $nums = '0123456789';
        $lowerAlphas = 'abcdefghijklmnopqrstuvwxyz';
        $upperAlphas = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $allAlnums = $nums . $lowerAlphas . $upperAlphas;

        while (--$length >= 0) {
            $ret .= $allAlnums[mt_rand(0, 61)];
        }

        return $ret;
    }

    /**
     * 数字转短代码
     *
     * @param int $num 整数
     * @return string
     */
    public static function numToCode($num)
    {
        $num = (int)$num;
        $ret = '';
        if ($num > 0) {
            $codes = "abcdefghjkmnpqrstuvwxyz23456789ABCDEFGHJKMNPQRSTUVWXYZ";
            while ($num > 53) {
                $index = $num % 54;
                $num = floor($num / 54) - 1;
                $ret = $codes[$index] . $ret;
            }
            $ret = $codes[(int)$num] . $ret;
        }

        return $ret;
    }

    /**
     * 短代码转数字
     *
     * @param string $code
     * @return int|null
     */
    public static function codeToNum($code)
    {
        $ret = null;
        if ($code) {
            $codes = "abcdefghjkmnpqrstuvwxyz23456789ABCDEFGHJKMNPQRSTUVWXYZ";
            $ret = 0;
            $i = $len = strlen($code);
            for ($j = 0; $j < $len; $j++) {
                $i--;
                $char = $code[$j];
                $pos = strpos($codes, $char);
                $ret += (pow(54, $i) * ($pos + 1));
            }
            $ret--;
        }

        return $ret;
    }

    /**
     * 10进制转26进制
     *
     * @param $n int
     * @return string
     */
    public static function fromDecTo26($n)
    {
        $ret = '';
        while ($n > 0) {
            $m = $n % 26;
            if ($m === 0) {
                $m = 26;
            }
            $n = ($n - $m) / 26;
            $ret = chr($m + 64) . $ret;
        }

        return $ret;
    }


    /**
     * 26进制转10进制
     *
     * @param $c26 string
     * @return int
     */
    public static function from26ToDec($c26)
    {
        $ret = 0;
        if (ctype_alpha($c26)) {
            $c26 = strtoupper($c26);
            for ($len = strlen($c26), $i = $len - 1, $j = 1; $i >= 0; $i--, $j *= 26) {
                $ret += (ord($c26[$i]) - 64) * $j;
            }
        }

        return $ret;
    }
}
