<?php
namespace Qf\Components\Facades;

/**
 * Class Auth
 * @package Qf\Components\Facades
 *
 * @method static bool check(array $user)
 * @method static int id()
 * @method static array user()
 * @method static bool isAuth()
 * @method static bool cancel()
 * @method static bool mark()
 */
class Auth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'auth';
    }
}