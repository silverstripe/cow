<?php


namespace SilverStripe\Cow\Tests\Model\Release;

use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Model\Release\Version;

class VersionTest extends PHPUnit_Framework_TestCase
{
    public function testLessThan()
    {
        $this->assertVersionLessThan('4.1.1-alpha1', '4.1.1');
        $this->assertVersionLessThan('4.1.0', '4.1.1-alpha1');
        $this->assertVersionsEqual('4.1.1', '4.1.1');

        $this->assertVersionLessThan('4.1.1-alpha1', '4.1.1-alpha2');
        $this->assertVersionLessThan('4.1.1-alpha1', '4.1.1-beta1');
        $this->assertVersionLessThan('4.1.1-alpha1', '4.1.1-rc1');
        $this->assertVersionLessThan('4.1.1-alpha1', '4.1.1-rc2');
        $this->assertVersionLessThan('4.1.1-beta2', '4.1.1-rc2');
    }

    public function testParse()
    {
        $version = new Version("v1.1.1");
        $this->assertEquals("1.1.1", $version->getValue());
        $version = new Version("v1.1.1-alpha1");
        $this->assertEquals("1.1.1-alpha1", $version->getValue());
    }

    public function assertVersionLessThan($left, $right)
    {
        $leftVersion = new Version($left);
        $rightVersion = new Version($right);
        // Test comparison can be inverted
        $this->assertLessThan(0, $leftVersion->compareTo($rightVersion), "$left is less than $right");
        $this->assertGreaterThan(0, $rightVersion->compareTo($leftVersion), "$right is greater than $left");
    }

    public function assertVersionsEqual($left, $right)
    {
        $leftVersion = new Version($left);
        $rightVersion = new Version($right);
        $this->assertEquals(0, $leftVersion->compareTo($rightVersion), "$left is equal to $right");
    }
}
