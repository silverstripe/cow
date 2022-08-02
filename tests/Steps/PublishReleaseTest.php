<?php

namespace SilverStripe\Cow\Tests\Steps;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\Version;
use SilverStripe\Cow\Steps\Release\PublishRelease;

class PublishReleaseTest extends TestCase
{
    public function discoverMostRecentTagProvider()
    {
        $tags = [
            new Version('0.1.0'),
            new Version('0.1.1'),
            new Version('0.1.2'),
            new Version('0.2.2'),
            new Version('1.0.0'),
            new Version('1.2.0'),
            new Version('1.2.3'),
            new Version('1.2.5'),
            new Version('1.2.999'),
            new Version('1.3.0'),
            new Version('1.3.1'),
            new Version('1.3.3'),
            new Version('1.3.2'),
            new Version('1.3.999-beta1'),
            new Version('1.3.999-rc1'),
            new Version('2.0.0'),
            new Version('2.0.1'),
            new Version('2.5.5'),
            new Version('3.999.999'),
            new Version('999.999.4'),
            new Version('999.999.5'),
            new Version('999.999.6'),
        ];
        return [
            // No tag matches the given major
            [
                'major' => 999,
                'minor' => 2,
                'tags' => $tags,
                'expected' => ''
            ],
            // No tag matches the given major/minor combination
            [
                'major' => 1,
                'minor' => 9999,
                'tags' => $tags,
                'expected' => ''
            ],
            // null minor gives the latest stable minor and patch for the given major (not pre-release)
            [
                'major' => 1,
                'minor' => null,
                'tags' => $tags,
                'expected' => '1.3.3'
            ],
            // with minor gives the latest patch for the given major/minor combo
            [
                'major' => 1,
                'minor' => 2,
                'tags' => $tags,
                'expected' => '1.2.999'
            ],
            // multi-digits are fine
            [
                'major' => 999,
                'minor' => 999,
                'tags' => $tags,
                'expected' => '999.999.6'
            ],
            // major 0 works fine
            [
                'major' => 0,
                'minor' => 1,
                'tags' => $tags,
                'expected' => '0.1.2'
            ],
            // minor 0 works fine
            [
                'major' => 2,
                'minor' => 0,
                'tags' => $tags,
                'expected' => '2.0.1'
            ],
        ];
    }

    /**
     * @dataProvider discoverMostRecentTagProvider()
     */
    public function testDiscoverMostRecentTag(int $major, ?int $minor, array $tags, string $expected)
    {
        $releaseStep = new PublishRelease(
            $this->getMockBuilder(Command::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(Project::class)->disableOriginalConstructor()->getMock()
        );
        $methodReflection = new ReflectionMethod($releaseStep, 'discoverMostRecentTag');
        $methodReflection->setAccessible(true);
        $this->assertEquals($expected, $methodReflection->invoke($releaseStep, $major, $minor, $tags));
    }
}
