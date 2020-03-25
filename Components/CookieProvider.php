<?php
namespace Qf\Components;

class CookieProvider extends Provider
{
    public $encryptionComName;
    public $domain;
    public $path;
    public $prefix;
    protected $isSecure;
    protected $cookie;
    protected $encryptionCom;

    public function __construct()
    {
        $this->domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $this->path = '/';
        if ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') || $_SERVER['SERVER_PORT'] == 443) {
            $this->isSecure = true;
        } else {
            $this->isSecure = false;
        }
        $this->cookie = &$_COOKIE;
        $this->prefix = '';
    }

    public function init()
    {
        if ($this->encryptionComName) {
            $this->encryptionCom = $this->com->{$this->encryptionComName};
        }
    }

    public function get($name, $default =  null)
    {
        $value = $default;
        $realName = $this->realKey($name);
        if (isset($this->cookie[$realName])) {
            $value = $this->cookie[$realName];
            if ($this->encryptionCom) {
                $value = $this->encryptionCom->decode($value);
            }
        }

        return $value;
    }

    public function set($name, $value = null, $expire = 0, $httpOnly = true)
    {
        $ret = false;

        if (is_null($value)) {
            $expire = -86400;
        } elseif ($value) {
            if ($this->encryptionCom) {
                $value = $this->encryptionCom->encode($value);
            }
        }
        $expire = (int)$expire;
        $httpOnly = (bool)$httpOnly;
        if (!headers_sent()) {
            $realName = $this->realKey($name);
            $ret = setcookie($realName, $value, time() + $expire, $this->path, $this->domain, $this->isSecure, $httpOnly);
            if ($ret) {
                $this->cookie[$realName] = $value;
            }
        }

        return $ret;
    }

    public function del($name)
    {
        return $this->set($name);
    }

    public function clearAll()
    {
        foreach ($this->cookie as $name => $value) {
            $origName = substr($name, strlen($this->prefix));
            if ($this->set($origName)) {
                unset($this->cookie[$name]);
            }
        }
    }

    protected function realKey($name)
    {
        return $this->prefix . $name;
    }
}