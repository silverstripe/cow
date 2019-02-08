<?php

namespace SilverStripe\Cow\Tests\Utility\Filter;

use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Utility\Filter\SupportedModuleFilter;

class SupportedModuleFilterTest extends PHPUnit_Framework_TestCase
{
    public function testFilterIncludesSupportedModules()
    {
        $input = [
            ['name' => 'foo', 'type' => 'supported-module'],
            ['name' => 'bar', 'type' => 'supported-module'],
            ['name' => 'baz', 'type' => 'supported-dependency'],
            ['name' => 'biz', 'type' => 'supported-theme'],
            ['name' => 'food', 'type' => 'unsupported-module'],
        ];

        $result = (new SupportedModuleFilter())->filter($input);
        $this->assertContains(['name' => 'foo', 'type' => 'supported-module'], $result);
        $this->assertContains(['name' => 'bar', 'type' => 'supported-module'], $result);
        $this->assertNotContains(['name' => 'baz', 'type' => 'supported-dependency'], $result);
        $this->assertNotContains(['name' => 'biz', 'type' => 'supported-theme'], $result);
        $this->assertNotContains(['name' => 'food', 'type' => 'unsupported-module'], $result);
    }
}
