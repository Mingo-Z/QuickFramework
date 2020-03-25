<?php
namespace Qf\Components;

class OpenSslCryptProvider extends Provider
{
    public $method;
    public $cryptKey;

    protected $ivLen;
    protected $keyLen;

    public function __construct()
    {
        $this->method = 'aes-256-cfb';
        $this->cryptKey = '';
        $this->keyLen = 32;
    }

    public function init()
    {
        $this->ivLen = openssl_cipher_iv_length($this->method);
    }

     protected function genRealIvKey()
     {
         $md5StrArray = [];
         $md5StrLen = $index = 0;
         while (($md5StrLen < ($this->ivLen + $this->keyLen))) {
             $willMd5Str = $this->cryptKey;
             if ($index > 0) {
                 $willMd5Str = $md5StrArray[$index - 1] . $this->cryptKey;
             }
             $md5Str = md5($willMd5Str, true);
             $md5StrArray[] = $md5Str;
             $md5StrLen += 16;
             $index++;
         }
         $md5AllStr = join('', $md5StrArray);
         $key = substr($md5AllStr, 0, $this->keyLen);
         $iv = substr($md5AllStr, $this->keyLen, $this->ivLen);

         return [$key, $iv];
    }

    public function encode($plaintext)
    {
        $newIv = openssl_random_pseudo_bytes($this->ivLen);
        $realIvKeyArray = $this->genRealIvKey();
        $realKey = $realIvKeyArray[0];
        $ciphertext = $newIv . openssl_encrypt($plaintext, $this->method, $realKey, OPENSSL_RAW_DATA, $newIv);

        return strtoupper(bin2hex($ciphertext));
    }

    public function decode($cipherText)
    {
        $plaintext = '';

        if (strlen($cipherText) > 16) {
            $cipherText = hex2bin($cipherText);
            if (strlen($cipherText) > $this->ivLen) {
                $iv = substr($cipherText, 0, $this->ivLen);
                $cipherText = substr($cipherText, $this->ivLen);
                $realIvKeyArray = $this->genRealIvKey();
                $realKey = $realIvKeyArray[0];
                $plaintext = openssl_decrypt($cipherText, $this->method, $realKey, OPENSSL_RAW_DATA, $iv);
            }
        }

        return $plaintext;
    }
}