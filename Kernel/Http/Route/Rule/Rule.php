<?php
namespace Qf\Kernel\Http\Route\Rule;

abstract class Rule
{
    /**
     * @var array
     */
    protected static $resolvedCache = [];

    /**
     * @param array|null $options
     *
     * @return array|null
     */
    abstract protected static function resolve(array $options = null);

    public static function getRouteComponents(array $options = null)
    {
        return static::resolve($options);
    }
}
