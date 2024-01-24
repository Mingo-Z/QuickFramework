<?php
namespace Qf\Utils;

class PasswordHash
{
    /**
     * Hash密码，未指定salt将自动生成，包含在返回值中，
     * 不建议指定salt。
     *
     * @param string $password
     * @param string|null $salt
     * @return false|string|null
     */
    static public function hash($password, $salt = null)
    {
        $options = [];
        if ($salt) {
            $options['salt'] = $salt;
        }
        return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    /**
     * 验证密码Hash值
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    static public function verify($password, $hash)
    {
        return password_verify($password, $hash);
    }
}

