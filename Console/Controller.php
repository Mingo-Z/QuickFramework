<?php
namespace Qf\Console;

use Qf\Kernel\Http\Controller as HttpController;

class Controller extends HttpController
{
    /**
     * 参数选项定义
     *
     * @var array
     */
    protected static $optionDefs = [
//        'test' => [ // 命令名称，与action匹配
//            [
//                'shortOptionName' => 'h', // 短名，必须
//                'longOptionName' => 'help', // 长名，可选
//                'required' => false, // 是否必须
//                'isHasValue' => false, // 是否有值
//                'isOptionalValue' => false, // 值是否可选，如果true则必须设置defaultValue
//                'valueDesc' => 'No value', // 值描述,
//                'defaultValue' => null, // 默认值
//                'comment' => 'Help manual', // 参数描述
//            ],
//        ],
    ];

    public function __call($method, array $arguments = null)
    {
        $command = new Command();
        $command->setName($method);

        if (static::$optionDefs && isset(static::$optionDefs[$method])) {
            foreach (static::$optionDefs[$method] as $optionDef) {
                $command->setOption($optionDef['shortOptionName'], $optionDef['longOptionName'] ?? '',
                $optionDef['required'] ?? false, $optionDef['isHasValue'] ?? false, $optionDef['isOptionalValue'] ?? false,
                $optionDef['valueDesc'] ?? '', $optionDef['defaultValue'] ?? null, $optionDef['comment'] ?? '');
            }
        }
        $command->parse($this->app->request->getArgv());
        return parent::__call($method, [$command->getOptionValues()]);
    }
}
