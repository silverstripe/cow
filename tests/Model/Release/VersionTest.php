<?php

namespace SilverStripe\Cow\Tests\Model\Release;

use PHPUnit\Framework\TestCase;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\Version;

class VersionTest extends TestCase
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


    public function dependencyProvider()
    {
        return [
            // Stable version
            ['1.5.0', Library::DEPENDENCY_EXACT, '1.5.0@stable'],
            ['1.5.0', Library::DEPENDENCY_LOOSE, '~1.5.0@stable'],
            ['1.5.0', Library::DEPENDENCY_SEMVER, '^1.5.0@stable'],
            // RC version
            ['1.5.0-rc2', Library::DEPENDENCY_EXACT, '1.5.0-rc2'],
            ['1.5.0-rc2', Library::DEPENDENCY_LOOSE, '~1.5.0@rc'],
            ['1.5.0-rc2', Library::DEPENDENCY_SEMVER, '^1.5.0@rc'],
            // Beta version
            ['1.5.0-beta1', Library::DEPENDENCY_EXACT, '1.5.0-beta1'],
            ['1.5.0-beta1', Library::DEPENDENCY_LOOSE, '~1.5.0@beta'],
            ['1.5.0-beta1', Library::DEPENDENCY_SEMVER, '^1.5.0@beta'],
        ];
    }

    /**
     * @dataProvider dependencyProvider()
     * @param string $version
     * @param string $stability
     * @param string $constraint
     */
    public function testGetConstraint($version, $stability, $constraint)
    {
        $versionObj = new Version($version);
        $this->assertEquals($constraint, $versionObj->getConstraint($stability));
    }
}
