<?php
namespace Qf\Kernel;

class ExceptionErrorHandle
{
    public static function exceptionHandle($e) // Before php7 Exception class not extends Throwable
    {
        if ($e instanceof \Exception || $e instanceof \Throwable) {
            $message = sprintf('%s(%d) in line %d of the %s',
                'Exception: ' . $e->getMessage(), $e->getCode(), $e->getLine(), $e->getFile());
            if (!isDebug()) {
                Application::getCom()->errlog->error($message);
            } else {
                $backtrace = self::processBacktrace($e->getTrace());
                $backtrace[] = $message;
                self::dumpBacktrace($backtrace);
            }
            if ($e instanceof Exception) {
                switch ($e->getCode()) {
                    case Exception::HTTP_STATUS_CODE_404:
                        self::http404Handle($e);
                        break;
                    case Exception::HTTP_STATUS_CODE_403:
                        self::http403Handle($e);
                        break;
                    default:
                }
            }
        }
    }

    public static function errorHandle($errno, $error, $errFile = null, $errLine = null, array $errContext = null)
    {
        $message = sprintf('%s(%d) in line %d of the %s', $error, $errno, $errLine, $errFile);
        if (!isDebug()) {
            Application::getCom()->errlog->error($message);
        } else {
            $backtrace = self::processBacktrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
            $backtrace[] = $message;
            self::dumpBacktrace($backtrace);
        }

        return !isDebug();
    }

    protected static function dumpBacktrace(array $backtrace)
    {
        echo join(isPhpCommandMode() ? "\n" : "<br />", $backtrace);
    }

    public static function processBacktrace(array $backtrace, $jumpLevel = 0)
    {
        $newBacktrace = [];
        $backtrace = array_slice($backtrace, $jumpLevel);
        $backtrace = array_reverse($backtrace);
        foreach ($backtrace as $index =>  $entry) {
            $newBacktrace[$index] = "#$index ";
            if (isset($entry['file'])) {
                $newBacktrace[$index] .= $entry['file'] . '(' . $entry['line'] . '): ';
            }
            $newBacktrace[$index] .= (isset($entry['class']) ? $entry['class'] . '->' : '') . $entry['function'] . '()';
        }

        return $newBacktrace;
    }

    /**
     * 设置错误处理函数，$ignoreErrorReporting = true，不受error_reporting函数
     * 设置影响。
     *
     * @param bool $ignoreErrorReporting
     */
    public static function installHandle($ignoreErrorReporting = false)
    {
        $handleErrorLevel = E_ALL | E_STRICT;
        if (!$ignoreErrorReporting) {
            $errorLevel = error_reporting();
            if (!($errorLevel & E_NOTICE)) {
                $handleErrorLevel &= ~E_NOTICE;
            }
            if (!($handleErrorLevel & E_DEPRECATED)) {
                $handleErrorLevel &= ~E_DEPRECATED;

            }
        }

        set_error_handler(__CLASS__ . '::errorHandle', $handleErrorLevel);
        set_exception_handler(__CLASS__ . '::exceptionHandle');
    }

    protected static function http404Handle(Exception $e)
    {

    }

    protected static function http403Handle(Exception $e)
    {

    }
}