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
    protected static $runArgDefineArray;

    public function __call($command, array $arguments)
    {
        $command = strtolower($command);
        if (static::$runArgDefineArray) {
            if (!isset(static::$runArgDefineArray[$command])) {
                throw new Exception(static::class . ": $command is not a command");
            }
            $formattedArgs = isset($arguments[0]) ? (array)$arguments[0] : [];
            $formattedArgs = $this->formatRunArgs($command, $formattedArgs);
            if ($this->processingRunArgs($command, $formattedArgs)) {
                parent::__call($command, [$formattedArgs]);
            } else {
                throw new Exception('Command parameter passed error');
            }
        } else {
            parent::__call($command, $arguments);
        }
    }

    protected function processingRunArgs($command, array $args)
    {
        $isProcessed = true;
        foreach (static::$runArgDefineArray[$command] as $name => $def) {
            $isRequired = (isset($def['required']) && $def['required']);
            if ($isRequired && !isset($args[$name])) {
                $isProcessed = false;
                break;
            }
        }
        if (!$isProcessed) {
            self::usage($command);
        }

        return $isProcessed;
    }

    protected function formatRunArgs($command, array $args)
    {
        $formattedArgs = [];
        $formattedValue = null;
        foreach ($args as $name => $value) {
            if (isset(static::$runArgDefineArray[$command][$name])) {
                $def = static::$runArgDefineArray[$command][$name];
                switch ($def['type']) {
                    case 'int':
                        $formattedValue = (int)$value;
                        break;
                    case 'bool':
                        $formattedValue = true;
                        break;
                    default:
                        $formattedValue = $value;
                }
                if (isset($def['options']) && is_array($def['options']) && !in_array($formattedValue, $def['options'], true)) {
                    $formattedValue = null;
                }
                if (is_null($formattedValue) || $formattedValue === '') {
                    $isRequired = (isset($def['required']) && $def['required']);
                    if (!$isRequired && isset($def['defaultValue'])) {
                        $formattedValue = $def['defaultValue'];
                    }
                }
                if (!is_null($formattedValue) && $formattedValue !== '') {
                    $formattedArgs[$name] = $formattedValue;
                }
            }
        }

        return $formattedArgs;
    }

    protected static function usage($command)
    {
        Console::stdout('usage: ' . static::class . " $command\n");
        foreach (static::$runArgDefineArray[$command] as $name => $def) {
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