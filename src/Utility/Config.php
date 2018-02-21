<?php

namespace SilverStripe\Cow\Utility;

use Exception;

class Config
{
    /**
     * Load json config from path
     *
     * @param string $path
     * @param string $schemaPath Optional schema file to validate against
     * @return array
     */
    public static function loadFromFile($path, $schemaPath = null)
    {
        // Allow empty config
        if (!file_exists($path)) {
            return [];
        }
        $content = file_get_contents($path);
        $arrayData = self::parseContent($content, true);

        // Validate
        if ($schemaPath) {
            // Note: Parse as object (assoc = false) for validation
            $schema = static::loadFromFile($schemaPath);

            $objectData = self::parseContent($content, false);
            $validator = SchemaValidator::validate($objectData, $schema);

            if (!$validator->isValid()) {
                $errors = [];
                foreach ($validator->getErrors() as $error) {
                    $errors[] = $error['message'];
                }
                throw new Exception("Config file is invalid: " . implode(", ", $errors));
            }
        }

        return $arrayData;
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
        $content = self::encodeContents($data);
        file_put_contents($path, $content);
    }

    /**
     * @param string $content
     * @param bool $assoc
     * @return array
     * @throws Exception
     */
    public static function parseContent($content, $assoc = true)
    {
        $result = json_decode($content, $assoc);

        // Make sure errors are reported
        if (json_last_error()) {
            throw new Exception(json_last_error_msg());
        }
        return $result;
    }

    /**
     * JSON encode the given data
     *
     * @param array $data
     * @return string
     * @throws Exception
     */
    public static function encodeContents($data)
    {
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Make sure errors are reported
        if (json_last_error()) {
            throw new Exception(json_last_error_msg());
        }
        return $content;
    }
}
