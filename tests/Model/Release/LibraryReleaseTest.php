<?php

namespace SilverStripe\Cow\Tests\Model\Release;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;

class LibraryReleaseTest extends PHPUnit_Framework_TestCase
{
    public function testConstructionSetsPriorVersion()
    {
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $library */
        $library = $this->createMock(Library::class);
        $version = new Version('1.2.3');
        $priorVersion = new Version('1.1.0');

        $release = new LibraryRelease($library, $version, $priorVersion);
        $this->assertSame($priorVersion, $release->getPriorVersion());
    }

    public function testGetPriorVersionFromExistingTags()
    {
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $library */
        $library = $this->createMock(Library::class);
        $library->expects($this->once())->method('getTags');

        $priorVersion = new Version('1.1.0');
        /** @var Version|PHPUnit_Framework_MockObject_MockObject $version */
        $version = $this->createMock(Version::class);
        $version->expects($this->once())->method('getPriorVersionFromTags')->willReturn($priorVersion);

        $release = new LibraryRelease($library, $version);
        $this->assertSame(
            $priorVersion,
            $release->getPriorVersion(true),
            'Fallback to getting the prior version from current versions list of tags'
        );
    }

    /**
     * Asserts that prior versions can be found via composer, not just by github tags
     * for example:
     *  given a module with 1.0.0 as the latest tag
     *  and the latest release was 0.9.0
     *  the prior version will be 0.9.0 for that module, not 1.0.0
     */
    public function testGetPriorVersionFromComposer()
    {
//        create our child library
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $library */
        $childLibrary = $this->getMockBuilder(Library::class)
            ->disableOriginalConstructor()
            ->setMethods(['getName'])
            ->getMock();

        $childLibrary->expects($this->any())->method('getName')->willReturn('some/module');

//        create our project, containing the child library
        /** @var Project|PHPUnit_Framework_MockObject_MockObject $project */
        $project = $this->getMockBuilder(Project::class)
            ->disableOriginalConstructor()
            ->setMethods(['getComposerData', 'getHistoryComposerData', 'isChildLibrary', 'getChildren'])
            ->getMock();

        $project->expects($this->any())->method('getComposerData')->willReturn([
            'name' => 'my-project',
            'require' => [
                'some/module' => '1.0.0'
            ]
        ]);

        $project->expects($this->any())->method('getHistoryComposerData')->willReturn([
            'name' => 'my-project',
            'require' => [
                'some/module' => '0.9.0'
            ]
        ]);

        $project->expects($this->any())->method('getChildren')->willReturn([
            $childLibrary
        ]);

//        setup the library release
        /** @var LibraryRelease|PHPUnit_Framework_MockObject_MockObject $library */
        $parentRelease = $this->getMockBuilder(LibraryRelease::class)
            ->setConstructorArgs([$project, new Version('1.1.0')])
            ->setMethods(['getPriorVersion'])
            ->getMock();
        $parentRelease->expects($this->any())->method('getPriorVersion')->willReturn('1.0.0');

        // assert the version uses composer data
        $priorRelease = $parentRelease->getPriorVersionForChild($childLibrary);
        $priorReleaseNumber = $priorRelease->getValue();
        $this->assertEquals('0.9.0', $priorReleaseNumber, 'Uses composer for prior module versions');
    }

    public function testCountAllItems()
    {
        /** @var Library[]|PHPUnit_Framework_MockObject_MockObject[] $libraries */
        $libraries = [
            $this->createMock(Library::class),
            $this->createMock(Library::class),
            $this->createMock(Library::class),
            $this->createMock(Library::class),
        ];
        $libraries[0]->method('getName')->willReturn('silverstripe/installer');
        $libraries[1]->method('getName')->willReturn('silverstripe/recipe-core');
        $libraries[2]->method('getName')->willReturn('silverstripe/assets');
        $libraries[3]->method('getName')->willReturn('silverstripe/framework');

        $parent = new LibraryRelease($libraries[0], new Version('1.2.3'));
        $child1 = new LibraryRelease($libraries[1], new Version('2.3.4'));
        $child2 = new LibraryRelease($libraries[2], new Version('2.3.4'));
        $grandchild = new LibraryRelease($libraries[3], new Version('2.3.4'));

        $child1->addItem($grandchild);
        $parent->addItems([$child1, $child2]);

        $this->assertSame(3, $parent->countAllItems(false));
        $this->assertSame(4, $parent->countAllItems(true));
    }
}
