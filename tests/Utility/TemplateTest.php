<?php

namespace SilverStripe\Cow\Tests\Utility;

use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Utility\Template;

class TemplateTest extends PHPUnit_Framework_TestCase
{
    public function testRendersTemplateWithContext()
    {
        $template = "# Release {{ version }}";
        $context = ['version' => '1.0.0'];
        $expected = "# Release 1.0.0";

        $output = (new Template())->renderTemplateStringWithContext($template, $context);

        $this->assertEquals($output, $expected);
    }
}
