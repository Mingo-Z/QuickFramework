<?php
namespace Qf\Utils;

class NetworkHelper
{
    public static function isLan($ip)
    {
        return isLanIP($ip);
    }

    /**
     * 判断IP是否在网段内
     *
     * @param string $ip
     * @param string $network
     * @return bool
     */
    public static function inNetwork($ip, $network)
    {
        $ret = false;

        $longIp = ip2long($ip);
        $segments = explode('/', $network);
        if ($longIp && count($segments) == 2) {
            $networkBeginLongIp = ip2long($segments[0]);
            if ($networkBeginLongIp) {
                $networkEndLongIp = $networkBeginLongIp + pow(2, 32 - $segments[1]);
                if ($longIp >= $networkBeginLongIp && $longIp <= $networkEndLongIp) {
                    $ret = true;
                }
            }
        }

        return $ret;
    }

    public static function isIpV4($ip)
    {
//        return preg_match('@\d{2,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}@', $ip);
        return (false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4));
    }

    public static function isIpV6($ip)
    {
        return (false !== filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6));
    }
}