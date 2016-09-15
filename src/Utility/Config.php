<?php

namespace SilverStripe\Cow\Utility;

use Exception;

class Config
{
    public static function loadFromFile($path) {
        // Allow empty config
        if (!file_exists($path)) {
            return [];
        }
        $result = json_decode(file_get_contents($path), true);

        // Make sure errors are reported
        if (json_last_error()) {
            throw new Exception(json_last_error_msg());
        }
        return $result;
    }
}