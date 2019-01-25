<?php

namespace SilverStripe\Cow\Utility\Filter;

use SilverStripe\Cow\Utility\FilterInterface;

class SupportedModuleFilter implements FilterInterface
{
    /**
     * @var string
     */
    const TYPE_SUPPORTED_MODULE = 'supported-module';

    /**
     * @var string
     */
    const TYPE_SUPPORTED_DEPENDENCY = 'supported-dependency';

    /**
     * Filters an array of modules by "supported-module" types
     *
     * @param array $input
     * @return array
     */
    public function filter(array $input)
    {
        return array_filter($input, function ($module) {
            return isset($module['type']) && $module['type'] === self::TYPE_SUPPORTED_MODULE;
        });
    }
}
