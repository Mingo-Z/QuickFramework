<?php
namespace Qf\Console;

use Qf\Kernel\Exception;
use Qf\Kernel\Http\Controller as HttpController;

class Controller extends HttpController
{
    /**
     * @var array
     *
    [
      'command' => [
          'arg0' => [
          'required' => true,
          'options' => [],
          'prefix' => '--', // -,--
          'type' => 'int', // int,bool,string
          'defaultValue' => '',
          'comment' => '',
          ],
          'arg...'
        ],
        ...
    ]
     *
     */
    protected static $optionDefs;

    public function __call($command, array $arguments)
    {
        $command = strtolower($command);
        if (static::$optionDefs) {
            if (!isset(static::$optionDefs[$command])) {
                throw new Exception(static::class . ": $command is not a command");
            }
            $runCmdArgs = isset($arguments[0]) ? (array)$arguments[0] : [];
            $formattedRunCmdArgs = $this->formatRunCmdArgs($command, $runCmdArgs);
            if ($this->checkRunCmdArgs($command, $formattedRunCmdArgs)) {
                parent::__call($command, [$formattedRunCmdArgs]);
            } else {
                throw new Exception('Command parameter passed error');
            }
        } else {
            parent::__call($command, $arguments);
        }
    }

    protected function checkRunCmdArgs($command, array $args)
    {
        $isOk = true;
        foreach (static::$optionDefs[$command] as $optionName => $optionDef) {
            $isRequired = (isset($optionDef['required']) && $optionDef['required']);
            if ($isRequired && !isset($args[$optionName])) {
                $isOk = false;
                break;
            }
        }
        if (!$isOk) {
            self::usage($command);
        }

        return $isOk;
    }

    protected function formatRunCmdArgs($command, array $args)
    {
        $formattedRunCmdArgs = [];
        $formattedOptionValue = null;

        foreach ($args as $optionName => $optionValue) {
            if (isset(static::$optionDefs[$command][$optionName])) {
                $optionDef = static::$optionDefs[$command][$optionName];
                switch ($optionDef['type']) {
                    case 'int':
                        $formattedOptionValue = (int)$optionValue;
                        break;
                    case 'bool':
                        $formattedOptionValue = true;
                        break;
                    default:
                        $formattedOptionValue = $optionValue;
                }
                if (isset($optionDef['options']) && is_array($optionDef['options']) && !in_array($formattedOptionValue, $optionDef['options'], true)) {
                    $formattedOptionValue = null;
                }
                if (is_null($formattedOptionValue) || $formattedOptionValue === '') {
                    $isRequired = (isset($optionDef['required']) && $optionDef['required']);
                    if (!$isRequired && isset($optionDef['defaultValue'])) {
                        $formattedOptionValue = $optionDef['defaultValue'];
                    }
                }
                if ($formattedOptionValue) {
                    $formattedRunCmdArgs[$optionName] = $formattedOptionValue;
                }
            }
        }

        return $formattedRunCmdArgs;
    }

    protected static function usage($command)
    {
        Console::stdout('usage: ' . static::class . " $command\n");
        foreach (static::$optionDefs[$command] as $name => $def) {
            $line = "\t{$def['prefix']}$name " . ((isset($def['required']) && $def['required']) ? 'required' : 'optional');
            if (isset($def['options']) && is_array($def['options'])) {
                $line .= ', options: ' . join(',', $def['options']);
            }
            if (isset($def['defaultValue']) && strlen($def['defaultValue'])) {
                $line = ', default value: ' . $def['defaultValue'];
            }
            if (isset($def['comment']) && $def['comment']) {
                $line .= ', ' . $def['comment'] . "\n";
            }

            Console::stdout($line);
        }
    }
}