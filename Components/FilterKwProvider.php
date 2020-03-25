<?php
namespace Qf\Components;

use Qf\Kernel\Exception;
use Qf\Utils\FileHelper;

class FilterKwProvider extends Provider
{
    /**
     * 禁止关键词数据文件
     *
     * @var string
     */
    public $dbFilePath;

    /**
     * 关键词分隔符，默认为"\n"
     *
     * @var string
     */
    public $separator;

    /**
     * 关键词数组
     *
     * @var array
     */
    protected $kws;


    public function __construct()
    {
        $this->separator = PHP_EOL;
        $this->kws = [];
    }

    public function init()
    {
        if (!$this->dbFilePath || !is_readable($this->dbFilePath)) {
            throw new Exception("Keyword data file does not exist or is not readable");
        }
    }

    protected function parseKwDbFile()
    {
        $fileContent = FileHelper::readLocalFileN($this->dbFilePath);
        $this->kws = explode($this->separator, $fileContent);
    }

    public function isContainsKw($string)
    {
        $bool = true;
        if ($string) {
            foreach ($this->kws as $kw) {
                if (($bool = false !== stripos($string, $kw))) {
                    break;
                }
            }
        }

        return $bool;
    }

    public function sanitize($string)
    {
        $strLen = strlen($string);
        if ($string) {
            foreach ($this->kws as $kw) {
                if (strlen($kw) <= $strLen) {
                    $string = str_replace($kw, str_repeat('*', mb_strlen($kw)), $string);
                }
            }
        }

        return $string;
    }

}