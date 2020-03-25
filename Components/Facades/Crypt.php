<?php
namespace Qf\Components\Facades;

/**
 * Class Crypt
 * @package Qf\Components\Facades
 *
 * @method static string encode($plaintext)
 * @method static string decode($cipherText)
 */
class Crypt extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'crypt';
    }
}