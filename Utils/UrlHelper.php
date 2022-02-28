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
            $url = $separator . http_build_query($params);
        }

        return "<meta http-equiv=\"refresh\" content=\"$refreshTime; url=$url\">";
    }
}

