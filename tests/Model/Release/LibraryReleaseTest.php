<?php

namespace SilverStripe\Cow\Tests\Model\Release;

use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;

class LibraryReleaseTest extends PHPUnit_Framework_TestCase
{
    public function testConstructionSetsPriorVersion()
    {
        $library = $this->createMock(Library::class);
        $version = new Version('1.2.3');
        $priorVersion = new Version('1.1.0');

        $release = new LibraryRelease($library, $version, $priorVersion);
        $this->assertSame($priorVersion, $release->getPriorVersion());
    }

    public function testGetPriorVersionFromExistingTags()
    {
        $library = $this->createMock(Library::class);
        $library->expects($this->once())->method('getTags');

        $priorVersion = new Version('1.1.0');
        $version = $this->createMock(Version::class);
        $version->expects($this->once())->method('getPriorVersionFromTags')->willReturn($priorVersion);

        $release = new LibraryRelease($library, $version);
        $this->assertSame(
            $priorVersion,
            $release->getPriorVersion(true),
            'Fallback to getting the prior version from current versions list of tags'
        );
    }
}
