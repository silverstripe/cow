<?php

namespace SilverStripe\Cow\Tests\Utility;

use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Utility\SchemaValidator;

class SchemaValidatorTest extends PHPUnit_Framework_TestCase
{
    protected $cowSchema;

    protected function setUp()
    {
        parent::setUp();

        $this->cowSchema = file_get_contents(dirname(dirname(__DIR__)) . '/cow.schema.json');
    }

    public function testValidSchema()
    {
        $cowConfig = <<<JSON
{
  "child-stability-inherit": [
    "cwp/cwp",
    "cwp/cwp-core"
  ],
  "upgrade-only": [
    "silverstripe/cms",
    "silverstripe/framework",
    "silverstripe/siteconfig",
    "silverstripe/reports",
    "symbiote/silverstripe-gridfieldextensions"
  ],
  "vendors": [
    "cwp",
    "silverstripe",
    "symbiote"
  ]
}
JSON;

        $validator = SchemaValidator::validate(json_decode($cowConfig));

        $this->assertTrue($validator->isValid());
    }

    public function testInvalidSchema()
    {
        $cowConfig = <<<JSON
{
  "vendors": "all-the-things"
}
JSON;

        $validator = SchemaValidator::validate(json_decode($cowConfig));

        $this->assertFalse($validator->isValid());
    }
}
