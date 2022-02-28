<?php
namespace Qf\Utils;

class UrlHelper
{
    /**
     * 生成指定停留时间的URL跳转代码
     *
     * @param string $url 目标URL
     * @param int $refreshTime 跳转停留时间，单位：s，0=不停留
     * @param array|null $params URL参数
     * @return string
     */
    public static function genJumpHtmlCode($url, $refreshTime = 0, array $params  = null)
    {
        $refreshTime = (int)$refreshTime;
        if ($params) {
            $separator = (strpos($url, '?') !== false) ? '&' : '?';
            $url .= $separator . self::buildQuery($params);
        }

        return "<meta http-equiv=\"refresh\" content=\"$refreshTime; url=$url\">";
    }

    /**
     * 构造application/x-www-form-urlencoded格式参数
     *
     * @param array $params URL参数
     * @param string $separator 参数分隔符
     * @return string
     */
    public static function buildQuery(array $params, $separator = '&')
    {
        return http_build_query($params, '', $separator);
    }
}

