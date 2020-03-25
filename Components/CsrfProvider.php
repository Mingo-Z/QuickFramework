<?php
namespace Qf\Components;

use Qf\Components\Facades\Cache;

class CsrfProvider extends Provider
{
    public static function getToken($prefix = '', $expire = 300)
    {
        $token = md5(microtime(true) . uniqid($prefix, true));
        Cache::set('token_' . $token, time(), $expire);

        return $token;
    }

    public static function check($token)
    {
        $key = 'token_' . $token;
        $ret = Cache::get($key);
        if ($ret) {
            Cache::delete($key);
        }

        return (bool)$ret;
    }
}