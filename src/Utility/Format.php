<?php


namespace SilverStripe\Cow\Utility;

class Format
{

    /**
     * Format a string with named args
     *
     * @param string $format
     * @param array $arguments Arguments
     * @return string
     */
    public static function formatString($format, $arguments)
    {
        $result = $format;
        foreach ($arguments as $name => $value) {
            $result = str_replace('{' . $name . '}', $value, $result);
        }
        return $result;
    }
}
