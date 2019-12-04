<?php

namespace SilverStripe\Cow\Tests\Model\Release;

use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Model\Modules\Library;
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

    public function testGetChildPriorVersions()
    {
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $library */
        $library = $this->createMock(Library::class);
        $children = array_map(
            function($name) {
                $lib = $this->createMock(Library::class);
                $lib->method('getName')->willReturn($name);
                return $lib;
            },
            [
                'silverstripe/framework',
                'silverstripe/admin',
                'silverstripe/new-module',
                'silverstripe/loose-constraint'
            ]
        );

        $composerData = ['require' => [
            'silverstripe/framework' => '4.3.2',
            'silverstripe/admin' => '1.3.4',
            'silverstripe/deprecated-module' => '0.1.2',
            'silverstripe/loose-constraint' => '~1.2.3',
        ]];


        $library->method('getChildren')->willReturn($children);
        $library->method('getHistoryComposerData')->willReturn($composerData);

        $version = new Version('1.2.3');
        $priorVersion = new Version('1.1.0');

        $library->expects($this->once())->method('getChildren');
        $library->expects($this->once())->method('getHistoryComposerData')->with($priorVersion);

        $release = new LibraryRelease($library, $version, $priorVersion);

        $actual = $release->getChildPriorVersions();
        $this->assertCount(2, $actual,
            'deprecated-module and loose-constraint module are not be in the child prior version list');
        $this->assertEquals('4.3.2', $actual['silverstripe/framework']->getValue());
        $this->assertEquals('1.3.4', $actual['silverstripe/admin']->getValue());
        $this->assertEquals('4.3.2', $release->getPriorVersionForChild($children[0])->getValue());
        $this->assertEquals('1.3.4', $release->getPriorVersionForChild($children[1])->getValue());
        $this->assertEmpty($release->getPriorVersionForChild($children[2]));
        $this->assertEmpty($release->getPriorVersionForChild($children[3]));

        $actual = $release->getChildPriorVersions();
        $this->assertCount(2, $actual,'getChildPriorVersions hits the cache the second time');
    }
}
