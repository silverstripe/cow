<?php

namespace SilverStripe\Cow\Utility;

use Exception;

class Config
{
    /**
     * Load json config from path
     *
     * @param string $path
     * @return array
     */
    public static function loadFromFile($path)
    {
        // Allow empty config
        if (!file_exists($path)) {
            return [];
        }
        $content = file_get_contents($path);
        return self::parseContent($content);
    }

    /**
     * Save the given array to a json file
     *
     * @param string $path
     * @param array $data
     * @throws Exception
     */
    public static function saveToFile($path, $data)
    {
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        // Make sure errors are reported
        if (json_last_error()) {
            throw new Exception(json_last_error_msg());
        }

        file_put_contents($path, $content);
    }

    /**
     * @param string $content
     * @return array
     * @throws Exception
     */
    public static function parseContent($content)
    {
        $result = json_decode($content, true);

        // Make sure errors are reported
        if (json_last_error()) {
            throw new Exception(json_last_error_msg());
        }
        return $result;
    }
}
