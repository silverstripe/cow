<?php

namespace SilverStripe\Cow\Tests\Model\Modules;

use InvalidArgumentException;
use PHPUnit_Framework_MockObject_MockObject;
use PHPUnit\Framework\TestCase;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;

class LibraryTest extends TestCase
{
    public function testNonExistentLibrary()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Did you forget to run cow release:create\?/');
        new Library('some-directory-that/will/never_exist');
    }

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

        $this->assertIsArray($result);
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

    public function testGetChangelogTemplate()
    {
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $library */
        $library = $this->getMockBuilder(Library::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCowData'])
            ->getMock();

        $library->expects($this->exactly(2))->method('getCowData')->willReturnOnConsecutiveCalls(
            ['changelog-template' => '.changelog.md'],
            []
        );

        $this->assertEquals('.changelog.md', $library->getChangelogTemplatePath());
        $this->assertNull($library->getChangelogTemplatePath(), 'Should be null when undefined');
    }

    public function testGetCommitLinkReturnsFromCowConfig()
    {
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $mock */
        $mock = $this->getMockBuilder(Library::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCowData'])
            ->getMock();

        $mock->expects($this->once())->method('getCowData')->willReturn([
            'commit-link' => 'https://www.silverstripe.org/moo',
        ]);

        $result = $mock->getCommitLink('foo');
        $this->assertSame('https://www.silverstripe.org/moo', $result);
    }

    public function testGetCommitLinkFromGitHubSlug()
    {
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $mock */
        $mock = $this->getMockBuilder(Library::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCowData', 'getGithubSlug'])
            ->getMock();

        $mock->expects($this->once())->method('getCowData')->willReturn([]);
        $mock->expects($this->once())->method('getGithubSlug')->willReturn('silverstripe/cow');

        $result = $mock->getCommitLink('q1w2e3r4t5y6');
        $this->assertSame('https://github.com/silverstripe/cow/commit/q1w2e3r4t5y6', $result);
    }

    /**
     * @param string[] $remotes
     * @param string $expected
     * @dataProvider remoteCommitLinkProvider
     */
    public function testGetCommitLinkFromRemotes($remotes, $expected)
    {
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $mock */
        $mock = $this->getMockBuilder(Library::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCowData', 'getGithubSlug', 'getRemotes'])
            ->getMock();

        $mock->expects($this->once())->method('getCowData')->willReturn([]);
        $mock->expects($this->once())->method('getGithubSlug')->willReturn(null);
        $mock->expects($this->once())->method('getRemotes')->willReturn($remotes);

        $result = $mock->getCommitLink('q1w2e3r4t5y6');
        $this->assertSame($expected, $result);
    }

    /**
     * @return array[]
     */
    public function remoteCommitLinkProvider()
    {
        return [
            'https without .git extension and with invalid remote url first' => [
                [
                    'www.example.com/gets-skipped',
                    'https://github.com/silverstripe/cow',
                ],
                'https://github.com/silverstripe/cow/commit/q1w2e3r4t5y6',
            ],
            'https with .git extension' => [
                ['https://github.com/silverstripe/cow.git'],
                'https://github.com/silverstripe/cow/commit/q1w2e3r4t5y6',
            ],
            'ssh protocol' => [
                ['git@github.com:silverstripe/cow.git'],
                'https://github.com/silverstripe/cow/commit/q1w2e3r4t5y6',
            ],
            'no format detected' => [[], null],
        ];
    }

    public function testGetGithubSlugFromCowData()
    {
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $mock */
        $mock = $this->getMockBuilder(Library::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCowData'])
            ->getMock();

        $mock->expects($this->once())->method('getCowData')->willReturn([
            'github-slug' => 'silverstripe/moo',
        ]);

        $this->assertSame('silverstripe/moo', $mock->getGithubSlug());
    }

    /**
     * @param string[] $remotes
     * @param string $expected
     * @dataProvider remoteSlugProvider
     */
    public function testGetGithubSlugFromRemotes($remotes, $expected)
    {
        /** @var Library|PHPUnit_Framework_MockObject_MockObject $mock */
        $mock = $this->getMockBuilder(Library::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCowData', 'getRemotes'])
            ->getMock();

        $mock->expects($this->once())->method('getCowData')->willReturn([]);
        $mock->expects($this->once())->method('getRemotes')->willReturn($remotes);

        $this->assertSame($expected, $mock->getGithubSlug());
    }

    /**
     * @return array[]
     */
    public function remoteSlugProvider()
    {
        return [
            'http(s) github' => [
                ['https://github.com/silverstripe/cow.git'],
                'silverstripe/cow',
            ],
            'ssh github' => [
                ['git@github.com:silverstripe/cow.git'],
                'silverstripe/cow',
            ],
            'gitlab' => [
                ['https:/gitlab.cwp.govt.nz/foo/bar'],
                null,
            ],
            'no matching remote' => [[], null],
        ];
    }
}
