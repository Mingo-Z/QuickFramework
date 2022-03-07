<?php
namespace Qf\Components;

use Qf\Kernel\Exception;

/**
 * 配置文件管理器
 *
 * @version $Id: $
 */

class ConfigEntryProvider extends Provider
{
    protected $origArray;
    protected $entries;

    public function __construct()
    {
        $this->entries = array();
        $this->origArray = array();
    }

    public function init(array $entries = array())
    {
        if ($entries) {
            foreach ($entries as $key => $value) {
                $this->origArray[$key] = $value;
                if (is_array($value)) {
                    $object = new self();
                    $object->init($value);
                    $this->entries[$key] = $object;
                } else {
                    $this->entries[$key] = $value;
                }
            }
        }
        return $this;
    }

    public function toArray()
    {
        return $this->origArray;
    }

    public function __get($key)
    {
        return $this->entries[$key] ?? null;
    }
}

class ConfigFileManagerProvider extends Provider
{
    /**
     * PHP数组格式配置文件
     */
    const CONFIG_FILE_TYPE_PHP_ARRAY = 0;
    /**
     * JSON格式配置文件
     */
    const CONFIG_FILE_TYPE_JSON = 1;
    /**
     * XML格式配置文件
     */
    const CONFIG_FILE_TYPE_XML = 2;

    const CONFIG_FILE_TYPE_INI = 3;

    /**
     * 配置管理器核心配置文件
     *
     * @var string
     */
    public $configCoreFilePath;

    /**
     * 配置管理器核心配置，通过加载配置文件获取
     *
     * @var array
     */
    protected $configCoreArray;

    /**
     * 已加载的配置对象
     *
     * @var array
     */
    protected $objects;

    public function __construct()
    {
        $this->configCoreFilePath = '';
        $this->configCoreArray = array();
        $this->objects = array();
    }

    /**
     * 配置文件管理对象初始化
     *
     * @return void
     */
    public function init()
    {
        if (!$this->configCoreArray) {
            if ($this->configCoreFilePath && is_readable($this->configCoreFilePath)) {
                $this->configCoreArray = (array)require $this->configCoreFilePath;
            }
        }
    }

    public function __get($name)
    {
        return $this->retrieve($name);
    }

    /**
     * 加载指定名称的配置
     *
     * @param string $name 配置名称
     * @return ConfigEntry|null
     */
    protected function retrieve($name)
    {
        $name = strtolower($name);
        if (!isset($this->objects[$name]) && isset($this->configCoreArray[$name]) && is_array($this->configCoreArray[$name])) {
            $array = $this->configCoreArray[$name];
            $configFilePath = $array['configFilePath'] ?? null;
            if (!$configFilePath || !is_file($configFilePath)) {
                throw new Exception("$name configuration configFilePath item is missing or is not file");
            }
            $type = isset($array['type']) ? (int)$array['type'] : 0;
            $entries = null;
            switch ($type) {
                case self::CONFIG_FILE_TYPE_PHP_ARRAY:
                    $entries = $this->parsePhpArrayConfigFile($configFilePath);
                    break;
                case self::CONFIG_FILE_TYPE_JSON:
                    $entries = $this->parseJsonConfigFile($configFilePath);
                    break;
                case self::CONFIG_FILE_TYPE_INI:
                    $entries = $this->parseIniConfigFile($configFilePath);
                default:
            }
            if (is_array($entries)) {
                $object = new ConfigEntryProvider();
                $object->init($entries);
                $this->objects[$name] = $object;
            } else {
                throw new Exception("$configFilePath file parsing failed, please check the file format");
            }
        }

        return $this->objects[$name] ?? null;
    }

    protected function parseJsonConfigFile($file)
    {
        $entries = null;
        $fileContent = file_get_contents($file);
        if ($fileContent) {
            $entries = json_decode($fileContent, true);
        }

        return $entries;
    }

    /**
     * 解析PHP数组格式配置文件
     *
     * @param string $configFilePath 配置文件完整路径
     * @return array|null
     */
    protected function parsePhpArrayConfigFile($configFilePath)
    {
        $entries = null;
        if (is_readable($configFilePath)) {
            $ret = require $configFilePath; // return array();
            if ($ret && is_array($ret)) {
                $entries = $ret;
                $ret = null;
            }
        }
        return $entries;
    }

    /**
     * 解析ini格式文件
     *
     * @param string $file
     * @return array|false
     */
    protected function parseIniConfigFile($file)
    {
        return parse_ini_file($file, true, INI_SCANNER_NORMAL);
    }
}

