<?php

namespace SilverStripe\Cow\Tests\Model\Modules;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;

class LibraryTest extends PHPUnit_Framework_TestCase
{
    public function testSerialisePlanSavesPriorVersion()
    {
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $library */
        $library = $this->getMockBuilder(Library::class)
            ->disableOriginalConstructor()
            ->setMethods(['getComposerData'])
            ->getMock();

        $library->expects($this->any())->method('getComposerData')->willReturn([
            'name' => 'testrepo',
        ]);

        $version = new Version('1.2.3');
        $priorVersion = new Version('1.1.0');
        $plan = new LibraryRelease($library, $version, $priorVersion);

        $result = $library->serialisePlan($plan);

        $this->assertInternalType('array', $result);
        $this->assertArrayHasKey('testrepo', $result);
        $this->assertSame('1.1.0', $result['testrepo']['PriorVersion']);
    }

    public function testGetChangelogIncludeOtherChanges()
    {
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $library */
        $library = $this->getMockBuilder(Library::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCowData'])
            ->getMock();

        $library->expects($this->exactly(3))->method('getCowData')->willReturnOnConsecutiveCalls(
            ['github-slug' => 'silverstripe/cow'],
            ['github-slug' => 'silverstripe/cow', 'changelog-include-other-changes' => false],
            ['github-slug' => 'silverstripe/cow', 'changelog-include-other-changes' => true]
        );

        $this->assertNull($library->getChangelogIncludeOtherChanges(), 'Should be null when undefined');
        $this->assertFalse($library->getChangelogIncludeOtherChanges(), 'Should be false from config');
        $this->assertTrue($library->getChangelogIncludeOtherChanges(), 'Should be true from config');
    }
}
