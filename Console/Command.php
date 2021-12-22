<?php
namespace Qf\Console;

use Qf\Kernel\Exception;

class Command
{
    /**
     * 命令名称
     *
     * @var string
     */
    protected $name;

    /**
     * 参数定义
     *
     * @var array
     */
    protected $optionDefs = [];

    /**
     * 长短参数名映射关系
     *
     * @var array
     */
    protected $longShortOptionNameMap = [];

    /**
     * 参数解析结果
     *
     * @var array
     */
    protected $parsedOptionResults = [];

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * 定义参数
     *
     * @param string $shortOptName 参数短名称
     * @param string|null $longOptName 参数长名称
     * @param bool $required 是否必须
     * @param bool $isHasValue 是否有值
     * @param bool $isOptionalValue 值是否可选
     * @param string|null $valueDesc 参数值描述
     * @param mixed $defaultValue 默认值，参数值可选设置默认值
     * @param string|null $comment 描述
     * @return $this
     * @throws Exception
     */
    public function setOption(
        $shortOptName,
        $longOptName = null,
        $required = false,
        $isHasValue = false,
        $isOptionalValue = false,
        $valueDesc = null,
        $defaultValue = null,
        $comment =null
    )
    {
        if (!$this->name) {
            throw new Exception('Command name is not set');
        } elseif (!ctype_alpha($shortOptName)) {
            throw new Exception('The short parameter name must have, and can only be alphabetic characters');
        } elseif ($isHasValue && $isOptionalValue && !$defaultValue) {
            throw new Exception('Optional parameter must have default value');
        }

        $this->optionDefs[$shortOptName] = [
            'longName' => $longOptName,
            'required' => (bool)$required,
            'isHasValue' => (bool)$isHasValue,
            'isOptionalValue' => (bool)$isOptionalValue,
            'valueDesc' => $valueDesc,
            'defaultValue' => $defaultValue,
            'comment' => $comment,
        ];

        if ($longOptName) {
            $this->longShortOptionNameMap[$longOptName] = $shortOptName;
        }

        return $this;
    }

    /**
     * 获取指定参值
     *
     * @param string $name 参数名
     * @param bool $isLongName 是否是长名称，无参数定义时，该参数不生效
     * @return mixed|null
     */
    public function getOptionValue($name, $isLongName = false)
    {
        $value = null;

        if ($this->optionDefs) {
            $shortName = $isLongName ? ($this->longShortOptionNameMap[$name] ?? '') : $name;
            if ($shortName && isset($this->parsedOptionResults[$shortName])) {
                if ($this->parsedOptionResults[$shortName]) {
                    $value = $this->parsedOptionResults[$shortName];
                } elseif ($this->optionDefs[$shortName]['isOptionalValue']) {
                    $value = $this->optionDefs[$shortName]['defaultValue'];
                }
            }
        } elseif (isset($this->parsedOptionResults[$name])) {
            $value = $this->parsedOptionResults[$name];
        }

        return $value;
    }

    /**
     * 获取所有传入参数
     *
     * @return array
     */
    public function getOptionValues()
    {
        $values = [];
        if ($this->optionDefs) {
            foreach ($this->optionDefs as $key => $optionDef) {
                if (($value = $this->getOptionValue($key))) {
                    $values[$key] = $value;
                }
            }
        } else {
            $values = $this->parsedOptionResults;
        }

        return $values;
    }

    protected function check()
    {
        $errOptions = [];
        foreach ($this->optionDefs as $key => $optionDef) {
            if ($optionDef['required'] && !isset($this->parsedOptionResults[$key])) {
                $errOptions[$key] = $optionDef;
            } elseif (isset($this->parsedOptionResults[$key]) && $optionDef['isHasValue'] && !$this->getOptionValue($key)) {
                $errOptions[$key] = $optionDef;
            }
        }

        return $errOptions;
    }

    /**
     *无参数定义解析
     *
     * @param array $argv
     * @return $this
     */
    protected function parseNoOptionDefs(array $argv)
    {
        $argc = count($argv);
        if ($argc > 2) {
            $index = 0;
            while ($index < $argc) {
                $cntArg = $argv[$index];
                $optionName = null;
                $optionValue = null;
                if (strpos($cntArg, '-') !== false) {
                    $optionName = ltrim($cntArg, '-');
                    $nextArg = $argv[$index + 1] ?? null;
                    if ($nextArg && strpos($nextArg, '-') === false) {
                        $optionValue = $nextArg;
                        $index++;
                    } else {
                        $optionValue = true;
                    }
                }

                if ($optionName) {
                    $this->parsedOptionResults[$optionName] = $optionValue;
                }
                $index++;
            }
        }

        return $this;
    }

    /**
     * 参数解析
     *
     * @param array $argv
     * @return $this
     * @throws Exception
     */
    public function parse(array $argv)
    {
        return $this->optionDefs ? $this->parseHasOptionDefs($argv) : $this->parseNoOptionDefs($argv);
    }

    /**
     * 有参数定义解析
     *
     * @param array $argv
     * @return $this
     * @throws Exception
     */
    protected function parseHasOptionDefs(array $argv)
    {
        $shortOptionName = null;
        $index = 0;
        $argc = count($argv);

        while (++$index < $argc) {
            switch (($arg = $argv[$index])) {
                case !strncmp($arg, '--', 2):
                    $longOptionName = ltrim($arg, '-');
                    $shortOptionName = $this->longShortOptionNameMap[$longOptionName] ?? null;
                    break;
                case !strncmp($arg, '-', 1):
                    $arg = ltrim($arg, '-');
                    $shortOptionName = isset($this->optionDefs[$arg]) ? $arg : null;
                    break;
            }
            if ($shortOptionName) {
                $optionDef = $this->optionDefs[$shortOptionName];
                if ($optionDef['isHasValue']) {
                    $nextArg = $argv[$index + 1] ?? '';
                    if (strpos($nextArg, '-') === false) {
                        $this->parsedOptionResults[$shortOptionName] = $nextArg;
                        $index++;
                    } else {
                        $this->parsedOptionResults[$shortOptionName] = null;
                    }
                } else {
                    $this->parsedOptionResults[$shortOptionName] = true;
                }
            }
        }

        if (isset($this->longShortOptionNameMap['help']) && isset($this->parsedOptionResults['h'])
            && $this->parsedOptionResults['h'] === true) {
            $this->usage();
            exit();
        } elseif ($this->check()) {
            Console::stderr("Command parameter error\n");
            $this->usage();
            exit();
        }

        return $this;
    }

    public function usage()
    {
        $content = "{$this->name} command usage: \nOptions:";
        foreach ($this->optionDefs as $key => $optionDef) {
            $content .= "\n  -{$key}";
            if ($optionDef['longName']) {
                $content .= ", --{$optionDef['longName']}";
            }
            if ($optionDef['isHasValue']) {
                if ($optionDef['valueDesc']) {
                    $content .= $optionDef['isOptionalValue'] ? "<{$optionDef['valueDesc']}>" : "[{$optionDef['valueDesc']}]";
                }
            }
            $content .= "\t" . ($optionDef['comment'] ?? '') .
                ($optionDef['defaultValue'] ? "[default: {$optionDef['defaultValue']}]" : '');
        }

        Console::stdout("$content\n");
    }
}
