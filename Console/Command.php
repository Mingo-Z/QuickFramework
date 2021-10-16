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

    public function name($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * 定义参数
     *
     * @param string $shortOptName 参数短名称
     * @param string|null $longOptName 参数长名称
     * @param bool false $requireValue 是否必须
     * @param bool false $optionalValue 是否可选
     * @param string|null $valueDesc 参数值描述
     * @param mixed $defaultValue 默认值
     * @param string|null $comment 描述
     * @return $this
     * @throws Exception
     */
    public function action(
        $shortOptName,
        $longOptName = null,
        $requireValue = false,
        $optionalValue = false,
        $valueDesc = null,
        $defaultValue = null,
        $comment =null
    )
    {
        if (!$this->name) {
            throw new Exception('Command name is not set');
        } elseif (!ctype_alpha($shortOptName)) {
            throw new Exception('The short parameter name must have');
        }

        $this->optionDefs[$shortOptName] = [
            'longName' => $longOptName,
            'requireValue' => (bool)$requireValue,
            'optionalValue' => (bool)$optionalValue,
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
     * @param bool false $isLongName 是否是长名称
     * @return mixed|null
     */
    public function option($name, $isLongName = false)
    {
        $value = null;

        $shortName = $isLongName ? ($this->longShortOptionNameMap[$isLongName] ?? '') : $name;
        if (isset($this->optionDefs[$shortName])) {
            $value = $this->parsedOptionResults[$shortName] ?? ($this->optionDefs[$shortName]['defaultValue']);
        }

        return $value;
    }

    protected function checkOptions()
    {
        $errOptions = [];
        foreach ($this->optionDefs as $key => $option) {
            $value = $this->option($key);
            if (($option['requireValue']  || $option['optionalValue']) && !$value) {
                $errOptions[$key] = $option;
            }
        }

        return $errOptions;
    }

    /**
     * 解析参数
     *
     * @return $this
     * @throws Exception
     */
    public function parse()
    {
        if (!$this->optionDefs) {
            throw new Exception('Missing command parameter configuration');
        }

        $shortOptionName = null;
        $index = 0;
        while (++$index < $_SERVER['argc']) {
            switch (($arg = $_SERVER['argv'][$index])) {
                case !strncmp($arg, '--', 2):
                    $longOptionName = ltrim($arg, '-');
                    $shortOptionName = $this->longShortOptionNameMap[$longOptionName] ?? null;
                    break;
                case !strncmp($arg, '-', 1):
                    $arg = ltrim($arg, '-');
                    $shortOptionName = $this->optionDefs[$arg] ? $arg : null;
                    break;
            }
            if ($shortOptionName) {
                $option = $this->optionDefs[$shortOptionName];
                if ($option['requireValue'] || $option['optionalValue']) {
                    $nextArg = $_SERVER['argv'][$index + 1] ?? '';
                    if (strpos($nextArg, '-') === false) {
                        $this->parsedOptionResults[$shortOptionName] = $nextArg;
                        $index++;
                    }
                }
            }
        }

        if ($this->checkOptions()) {
            Console::stderr("Command parameter error\n");
            $this->usage();
        }

        return $this;
    }

    public function usage()
    {
        $content = "{$this->name} command usage: ";
        foreach ($this->optionDefs as $key => $option) {
            $content .= "\n\t-{$key}";
            if ($option['longName']) {
                $content .= ", --{$option['longName']}";
            }
            if ($option['requireValue'] || $option['optionalValue']) {
                if ($option['valueDesc']) {
                    $content .= $option['requireValue'] ? "<{$option['valueDesc']}>" : "[{$option['valueDesc']}]";
                }
            }
            $content .= "\t" . ($option['comment'] ?? '') .
                ($option['defaultValue'] ? "[default: {$option['defaultValue']}]" : '');
        }

        Console::stdout($content);
    }
}