<?php
namespace Qf\Kernel\Http;

interface Auth
{
    public function check(array $user);
    public function id();
    public function user();
    public function isAuth();
    public function cancel();
    public function mark($id, $username, $password);

}