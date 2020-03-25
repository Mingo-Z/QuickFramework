<?php
namespace Qf\Components;

use Qf\Components\Facades\Cookie;
use Qf\Kernel\Http\Auth;

class AuthProvider extends Provider implements Auth
{
    protected $user;
    protected $isAuth;

    public function init()
    {
        $this->user = [
            'id' => 0,
            'username' => '',
        ];
        $this->isAuth = false;
    }
    public function check(array $user)
    {
        $username = isset($user['username']) ? $user['username'] : '';
        $password = isset($user['password']) ? $user['password'] : '';
        $id = isset($user['id']) ? $user['id'] : 0;
        if (!$this->isAuth && $id && $username && $password) {
            list($clientId, $clientUsername, $clientPassword) = explode("\t", Cookie::get('auth'));
            if ($id === $clientId && !strcmp($username, $clientUsername) && !strcmp($password, $clientPassword)) {
                $this->user = $user;
                $this->isAuth = true;
            }
        }

        return $this->isAuth;
    }

    public function id()
    {
        return $this->user['id'];
    }

    public function user()
    {
        return $this->user;
    }

    public function isAuth()
    {
        return $this->isAuth;
    }

    public function cancel()
    {
        return Cookie::del('auth');
    }

    public function mark($id, $username, $password, $expire = 0)
    {
        return Cookie::set('auth', "$id\t$username\t$password", (int)$expire);
    }
}