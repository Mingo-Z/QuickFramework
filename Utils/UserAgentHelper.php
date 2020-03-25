<?php
namespace Qf\Utils;

class UserAgentHelper
{
    protected $rawUserAgent;

    private function __construct($ua = null)
    {
        if (!$ua) {
            $this->rawUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
    }

    public static function getInstance($ua = null)
    {
        $instance = null;
        if (!$instance) {
            $instance = new self($ua);
        }

        return $instance;
    }

    public function isMainPc()
    {
        return ($this->isWinOs() || $this->isMacOs());
    }

    public function isAppleWebKit()
    {
        return (!stripos($this->rawUserAgent, 'AppleWebKit') !== false);
    }

    public function isMobile()
    {
        return ($this->isIosMobile() || $this->isAndroidMobile());
    }

    public function isIosMobile()
    {
        return (stripos($this->rawUserAgent, 'iPhone') !== false);
    }

    public function isAndroidMobile()
    {
        return (stripos($this->rawUserAgent, 'Android') !== false);
    }

    public function isMacOs()
    {
        if (stripos($this->rawUserAgent, 'Macintosh') !== false ||
            stripos($this->rawUserAgent, 'Mac') !== false) {
            return true;
        }

        return false;
    }

    public function isBaiduSpider()
    {
        return (stripos($this->rawUserAgent, 'Baiduspider') !== false);
    }

    public function isGoogleSpider()
    {
        return (stripos($this->rawUserAgent, 'Googlebot') !== false);
    }

    public function isSpider()
    {
        return (bool)preg_match('@spider|Googlebot@i', $this->rawUserAgent);
    }

    public function isWinOs()
    {
        return (stripos($this->rawUserAgent, 'Windows') !== false);
    }

    public function isWeChat()
    {
        return (stripos($this->rawUserAgent, 'MicroMessenger') !== false);
    }

    public function isAliPay()
    {
        return (stripos($this->rawUserAgent, 'AlipayClient') !== false);
    }
}