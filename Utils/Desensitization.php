<?php
namespace Qf\Utils;

use Qf\Utils\Validation\Validation;

class Desensitization
{
    const DATA_TYPE_EMAIL = 1;
    const DATA_TYPE_MOBILE = 2;
    const DATA_TYPE_ID = 3;

    public static function make($data, $dataType)
    {
        switch ($dataType) {
            case self::DATA_TYPE_EMAIL:
                list($account, $sp) = explode('@', $data);
                $midNum = floor(strlen($account) / 2);
                $desData = self::mask($account, $midNum, $midNum) . "@$sp";
                break;
            case self::DATA_TYPE_MOBILE:
                // cn mobile format
                if (Validation::cnMobileOk($data)) {
                    $desData = self::mask($data, 3, 4);
                } else {
                    $midNum = floor(strlen($data) / 2);
                    $desData = self::mask($data, $midNum, $midNum);
                }
                break;
            case self::DATA_TYPE_ID:
                $desData = self::mask($data, 1, -1);
                break;
            default:
                $desData = $data;
        }

        return $desData;
    }

    public static function mask($data, $offset = 0, $length = 4)
    {
        $maskedData = $data;
        $dataLength = strlen($data);
        $offset = ($offset < 0) ? $dataLength + $offset : (int)$offset;
        $length = ($length < 0) ? $dataLength + $length : (int)$length;
        while ($length-- > 0) {
            if (!isset($maskedData[$offset])) {
                break;
            }
            $maskedData[$offset++] = '*';
        }

        return $maskedData;
    }

}