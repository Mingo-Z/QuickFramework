<?php
namespace Qf\Components\Facades;

/**
 * Class IdGenerator
 * @package Qf\Components\Facades
 *
 * @method static array getIds($num)
 * @method static int getId()
 */
class IdGenerator extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'idgenerator';
    }
}