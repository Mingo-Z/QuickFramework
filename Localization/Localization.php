<?php
namespace Qf\Localization;

class Localization
{
    protected $lang;

    protected static $packages;

    public function __construct($lang = 'zh-cn')
    {
        $this->lang = $lang;
        self::$packages = [];
    }

    public function setLocale($lang)
    {
        $this->lang = $lang;

        return $this;
    }

    public function getLocale()
    {
        return $this->lang;
    }

    public function isLocale($lang)
    {
        return $lang == $this->lang;
    }

    protected function loadPackageFile($name, $module = null)
    {
        $packageFile = AppPath . '/public/resource/lang/' . $this->lang . '/';
        if ($module) {
            $packageFile .= "$module/";
        }
        $packageFile .= "{$name}.php";
        $fileMd5 = null;
        if (is_file($packageFile)) {
            $fileMd5 = md5_file($packageFile);
            if (!isset(self::$packages[$fileMd5])) {
                self::$packages[$fileMd5] = include $packageFile;
            }
        }

        return $fileMd5 ? (self::$packages[$fileMd5] ?? []) : [];
    }

    public function translate($message, array $arguments = null, $module = null)
    {
        static $messagesCache = [];

        $messageMd5 = md5($message . json_encode($arguments) . $module);
        if (!isset($messagesCache[$messageMd5])) {
            $packageName = 'messages';
            if (strpos($message, '.') !== false) {
                list($packageName, $message) = explode('.', $message);
            }
            $messages = $this->loadPackageFile($packageName, $module);
            $langMessage = $message;
            if (isset($messages[$message])) {
                $langMessage = $messages[$message];
                if ($arguments) {
                    foreach ($arguments as $key => $value) {
                        $langMessage = str_replace(":$key", $value, $langMessage);
                    }
                }
            }
            $messagesCache[$messageMd5] = $langMessage;
        }

        return $messagesCache[$messageMd5];
    }

}