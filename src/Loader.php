<?php

namespace SilverStripe\Cow;

/**
 * Provides a base class for any utility classes, including things like singleton accessors
 */
abstract class Loader
{
    /**
     * @var Loader[]
     */
    protected static $instances = [];

    /**
     * Get a singleton instance
     *
     * @return static
     */
    public static function instance()
    {
        $class = get_called_class();

        if (!isset(static::$instances[$class])) {
            static::$instances[$class] = new $class();
        }
        return static::$instances[$class];
    }
}
