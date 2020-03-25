<?php
namespace Qf\Utils\Validation;

class Validation
{
    public static function emailOk($in)
    {
        return (false !== filter_var($in, FILTER_VALIDATE_EMAIL));
    }

    public static function mobileOk($in)
    {
        return (bool)preg_match('@^[1-9]\d{6,10}$@', $in);
    }

    public static function cnMobileOk($in)
    {
        return (bool)preg_match('@^1[3-9]\d{9}$@', $in);
    }

    public static function urlOk($in)
    {
        return (false !== filter_var($in, FILTER_VALIDATE_URL));
    }

    public static function __callStatic($name, $arguments)
    {
        $isOk = false;
        $name = str_replace('_', '', $name);
        $method = $name . 'Ok';
        if (method_exists(self::class, $method)) {
            $isOk = self::$method(...$arguments);
        }

        return $isOk;
    }

    /**
     * 密码不少于指定长度，并且需要同时含有字母和数字
     *
     * @param string $in
     * @param int $length
     * @return bool
     */
    public static function passwordOk($in, $length = 6)
    {
        $isOk = false;
        if (strlen($in) >= (int)$length) {
            if (preg_match('@[a-z]+@i', $in) && preg_match('@\d+@', $in)) {
                $isOk = true;
            }
        }

        return $isOk;
    }

    public static function currencyOk($in)
    {
        return (bool)preg_match('@^-?\d\d*(\.\d+)?$@', $in);
    }

    /**
     * International area code
     *
     * @param string $in
     */
    public static function internalAreaCodeOk($in)
    {
        return (bool)preg_match('@^[1-9]\d{0,4}@', $in);
    }

    public static function alphaOk($in)
    {
        return ctype_alpha($in);
    }

    public static function alphaNumOk($in)
    {
        return ctype_alnum("$in");
    }

    public static function digitOk($in)
    {
        return ctype_digit("$in");
    }
}