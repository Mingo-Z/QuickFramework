<?php
namespace Qf\Components\Facades;

/**
 * Class Config
 * @package Qf\Components\Facades
 */
class Config extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'config';
    }
}