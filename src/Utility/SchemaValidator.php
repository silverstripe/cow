<?php

namespace SilverStripe\Cow\Utility;

use Exception;
use JsonSchema\Validator;

/**
 * Validates input data against the cow schema file
 */
class SchemaValidator
{
    /**
     * @var string
     */
    const SCHEMA_FILENAME = 'cow.schema.json';

    /**
     * Loads and return the cow schema
     *
     * @return array
     * @throws Exception
     */
    public static function getSchema()
    {
        $schemaPath = dirname(dirname(__DIR__)) . '/' . self::SCHEMA_FILENAME;
        return Config::loadFromFile($schemaPath);
    }

    /**
     * Validate the incoming object data against the given schema. If no schema is provided then the default cow
     * schema will be loaded.
     *
     * @param array $objectData
     * @param array $schema If not provided, the default will be used
     * @return Validator
     */
    public static function validate($objectData, $schema = null)
    {
        if (is_null($schema)) {
            $schema = self::getSchema();
        }

        $validator = new Validator();
        $validator->validate($objectData, $schema);

        return $validator;
    }
}
