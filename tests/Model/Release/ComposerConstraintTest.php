<?php

namespace SilverStripe\Cow\Tests\Model\Release;

use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Model\Release\ComposerConstraint;
use SilverStripe\Cow\Model\Release\Version;

class ComposerConstraintTest extends PHPUnit_Framework_TestCase
{
    public function testParseSemver() {
        $constraint = new ComposerConstraint('^4.1.1');
        $this->assertEquals('4.1.1-alpha1', $constraint->getMinVersion()->getValue());
        $this->assertEquals('4.99999.99999', $constraint->getMaxVersion()->getValue());

        $constraint = new ComposerConstraint('^4.1');
        $this->assertEquals('4.1.0-alpha1', $constraint->getMinVersion()->getValue());
        $this->assertEquals('4.99999.99999', $constraint->getMaxVersion()->getValue());

        $constraint = new ComposerConstraint('^4');
        $this->assertEquals('4.0.0-alpha1', $constraint->getMinVersion()->getValue());
        $this->assertEquals('4.99999.99999', $constraint->getMaxVersion()->getValue());
    }

    public function testParseLoose() {
        $constraint = new ComposerConstraint('~4.1.1');
        $this->assertEquals('4.1.1-alpha1', $constraint->getMinVersion()->getValue());
        $this->assertEquals('4.1.99999', $constraint->getMaxVersion()->getValue());

        $constraint = new ComposerConstraint('~4.1');
        $this->assertEquals('4.1.0-alpha1', $constraint->getMinVersion()->getValue());
        $this->assertEquals('4.99999.99999', $constraint->getMaxVersion()->getValue());

        $constraint = new ComposerConstraint('~4');
        $this->assertEquals('4.0.0-alpha1', $constraint->getMinVersion()->getValue());
        $this->assertEquals('99999.99999.99999', $constraint->getMaxVersion()->getValue());
    }

    public function testParseDev() {
        $constraint = new ComposerConstraint('4.1.x-dev');
        $this->assertEquals('4.1.0-alpha1', $constraint->getMinVersion()->getValue());
        $this->assertEquals('4.1.99999', $constraint->getMaxVersion()->getValue());

        $constraint = new ComposerConstraint('4.x-dev');
        $this->assertEquals('4.0.0-alpha1', $constraint->getMinVersion()->getValue());
        $this->assertEquals('4.99999.99999', $constraint->getMaxVersion()->getValue());

        $constraint = new ComposerConstraint('4.1.*-dev');
        $this->assertEquals('4.1.0-alpha1', $constraint->getMinVersion()->getValue());
        $this->assertEquals('4.1.99999', $constraint->getMaxVersion()->getValue());

        $constraint = new ComposerConstraint('4.*-dev');
        $this->assertEquals('4.0.0-alpha1', $constraint->getMinVersion()->getValue());
        $this->assertEquals('4.99999.99999', $constraint->getMaxVersion()->getValue());
    }

    public function testParseSelfVersion() {
        $constraint = new ComposerConstraint('self.version', new Version('4.1.1'));
        $this->assertEquals('4.1.1', $constraint->getMinVersion()->getValue());
        $this->assertEquals('4.1.1', $constraint->getMaxVersion()->getValue());
    }

    public function filterVersionsProvider() {
        return [
            // Test exact version
            ['4.1.1', null, ['4.1.0', '4.1.1', '4.1.1-alpha1', '4.1.2', '4.2.0', '5.0.0'], ['4.1.1']],
            ['4.1.1', null, ['4.1.0', '4.1.2', '5.0.0', '4.2.0'], []],
            // Test semver versions
            [
                '~4.1.1',
                null,
                ['4.1.0', '4.1.1', '4.1.1-alpha1', '4.1.2', '4.2.0', '5.0.0'],
                ['4.1.1', '4.1.1-alpha1', '4.1.2'],
            ],
            [
                '^4.1.1',
                null,
                ['4.1.0', '4.1.1', '4.1.1-alpha1', '4.1.2', '4.2.0', '5.0.0'],
                ['4.1.1', '4.1.1-alpha1', '4.1.2', '4.2.0'],
            ],
        ];
    }

    /**
     * @dataProvider filterVersionsProvider()
     */
    public function testFilterVersions($constraint, $parentVersion, $input, $output) {
        $constraintObject = new ComposerConstraint($constraint, $parentVersion);
        $inputVersions = [];
        foreach($input as $tag) {
            $inputVersions[$tag] = new Version($tag);
        }
        $result = $constraintObject->filterVersions($inputVersions);
        $this->assertEquals($output, array_keys($result), "Version constraint $constraint filters the given list");
    }
}
