<?php
/**
 * RSA加解密实现
 */
namespace Qf\Utils;

class RSAHelper
{
    const PUBLIC_TYPE_KEY = 0;
    const PRIVATE_TYPE_KEY = 1;

    protected static function getPublicKey($key)
    {
        return openssl_get_publickey($key);
    }

    protected static function getPrivateKey($key)
    {
        return openssl_get_privatekey($key);
    }

    public static function encodeByPublicKey($publicKey, $data)
    {
        return self::encode($publicKey, $data, self::PUBLIC_TYPE_KEY);
    }

    public static function decodeByPublicKey($publicKey, $data)
    {
        return self::decode($publicKey, $data, self::PUBLIC_TYPE_KEY);
    }

    public static function encodeByPrivateKey($privateKey, $data)
    {
        return self::encode($privateKey, $data, self::PRIVATE_TYPE_KEY);
    }

    public static function decodeByPrivateKey($privateKey, $data)
    {
        return self::decode($privateKey, $data, self::PRIVATE_TYPE_KEY);
    }

    protected static function encode($key, $data, $type = self::PUBLIC_TYPE_KEY)
    {
        $encodedData = null;
        $keyResource = null;
        $encodeFunc = null;

        switch ($type) {
            case self::PUBLIC_TYPE_KEY:
                $keyResource = self::getPublicKey($key);
                $encodeFunc = 'openssl_public_encrypt';
                    break;
                case self::PRIVATE_TYPE_KEY:
                    $keyResource = self::getPrivateKey($key);
                    $encodeFunc = 'openssl_private_encrypt';
                    break;
        }

        if ($keyResource) {
            $keyDetails = openssl_pkey_get_details($keyResource);
            $maxChunkBytes = $keyDetails['bits']/8 - 11;
            $dataLength = strlen($data);
            for ($pos = 0; $pos < $dataLength; $pos += $maxChunkBytes) {
                $plainText = substr($data, $pos, $maxChunkBytes);
                $cipherText = null;
                $encodeFunc($plainText, $cipherText, $keyResource);
                $encodedData .= $cipherText;
            }
        }

        return $encodedData ? base64_encode($encodedData) : null;
    }

    protected static function decode($key, $encodedData, $type = self::PRIVATE_TYPE_KEY)
    {
        $decodeFunc = null;
        $decodedData = null;
        $keyResource = null;
        $data = base64_decode($encodedData);

        if ($data) {
            switch ($type) {
                case self::PUBLIC_TYPE_KEY:
                    $decodeFunc = 'openssl_public_decrypt';
                    $keyResource = self::getPublicKey($key);
                    break;
                case self::PRIVATE_TYPE_KEY:
                    $decodeFunc = 'openssl_private_decrypt';
                    $keyResource = self::getPrivateKey($key);
                    break;
            }
            if ($keyResource) {
                $decodeFunc($data, $decodedData, $keyResource);
            }
        }

        return $decodedData;
    }
}