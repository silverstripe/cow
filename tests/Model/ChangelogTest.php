<?php

namespace SilverStripe\Cow\Tests\Model;

use DateTime;
use InvalidArgumentException;
use Gitonomy\Git\Commit;
use Gitonomy\Git\Log;
use Gitonomy\Git\Repository;
use PHPUnit\Framework\TestCase;
use SilverStripe\Cow\Model\Changelog\Changelog;
use SilverStripe\Cow\Model\Changelog\ChangelogLibrary;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ChangelogTest extends TestCase
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Changelog
     */
    protected $changelog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = new NullOutput();

        $log = $this->createMock(Log::class);

        $repository = $this->createMock(Repository::class);
        $repository->method('getLog')->willReturn($log);

        $commits = [
            new Commit($repository, sha1('foo'), [
                'shortHash' => 'shortfoo',
                'subjectMessage' => 'FIX Changed foo for bar',
                'authorName' => 'Alex Atkins',
                'authorDate' => new DateTime('2014-12-25 09:02:10'),
            ]),
            new Commit($repository, sha1('bar'), [
                'shortHash' => 'shortbar',
                'subjectMessage' => 'API Remove baz',
                'authorName' => 'Ryan Reid',
                'authorDate' => new DateTime('1990-05-02 04:20:00'),
            ]),
            new Commit($repository, sha1('baz'), [
                'shortHash' => 'shortbaz',
                'subjectMessage' => 'NEW Added foobar',
                'authorName' => 'Leslie Lolcopter',
                'authorDate' => new DateTime('2015-01-03 12:15:31'),
            ]),
            new Commit($repository, sha1('boo'), [
                'shortHash' => 'shortboo',
                'subjectMessage' => 'Some uncategorised commit',
                'authorName' => 'Val Vulcan',
                'authorDate' => new DateTime('2015-04-20 04:20:00'),
            ]),
            new Commit($repository, sha1('gee'), [
                'shortHash' => 'shortgee',
                'subjectMessage' => 'MNT Some maintenance commit',
                'authorName' => 'Val Vulcan',
                'authorDate' => new DateTime('2015-04-20 04:21:00'),
            ]),
            new Commit($repository, sha1('damn'), [
                'shortHash' => 'shortdamn',
                'subjectMessage' => '[CVE-2015-1234] Someone forgot the coffee!',
                'authorName' => 'Charlie Charizard',
                'authorDate' => new DateTime('2015-04-20 04:20:00'),
            ]),
            new Commit($repository, sha1('anotherdamn'), [
                'subjectMessage' => '[CVE-2015-1234] Someone forgot the coffee!',
                'authorName' => 'Charlie Charizard',
                'authorDate' => new DateTime('2015-04-20 04:20:00'),
            ]),
        ];
        $log->method('getCommits')->willReturn($commits);

        $library = $this->createMock(Library::class);
        $library->method('getTags')->willReturn(['0.1.0', '0.1.1', '0.5.0']);
        $library->method('getRepository')->willReturn($repository);
        $library->method('getCommitLink')->will($this->returnCallback(function ($argument) {
            return 'http://example.com/' . $argument;
        }));
        $library->method('getChangelogTemplatePath')->willReturn('.template.md');

        $version = new Version('1.0.0');
        $priorVersion = new Version('0.5.0');

        $release = new LibraryRelease($library, $version);

        $changelogLibrary = new ChangelogLibrary($release, $priorVersion);

        $this->changelog = new Changelog($changelogLibrary);
    }

    public function testGetMarkdownThrowsExceptionOnInvalidType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown changelog format foobar');
        $this->changelog->getMarkdown($this->output, 'foobar');
    }

    public function testGetMarkdownGroupedContainsHeadings()
    {
        $result = $this->changelog->getMarkdown($this->output, Changelog::FORMAT_GROUPED);

        $this->assertStringContainsString('## Change Log', $result);
        $this->assertStringContainsString('### Security', $result);
        $this->assertStringContainsString('### Bugfixes', $result);
        $this->assertStringContainsString('### Features and Enhancements', $result);
        $this->assertStringContainsString('### API Changes', $result);
    }

    public function testGetMarkdownFlatDoesNotContainHeadings()
    {
        $result = $this->changelog->getMarkdown($this->output, Changelog::FORMAT_FLAT);

        $this->assertStringNotContainsString('## Change Log', $result);
        $this->assertStringNotContainsString('### Bugfixes', $result);
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogContainsCommitDates($type)
    {
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertStringContainsString('2014-12-25', $result, 'Alex\'s commit date');
        $this->assertStringContainsString('1990-05-02', $result, 'Ryan\'s commit date');
        $this->assertStringContainsString('2015-01-03', $result, 'Leslie\'s commit date');
        $this->assertStringContainsString('2015-04-20', $result, 'Charlie\'s commit date');
    }

    /**
     * Short and long hashes should be in the changelogs - short is what we show to the user, long is used
     * in the URLs. Long hashes are checkes in testChangelogContainsLinkToCommitInRepository.
     *
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogContainsShortHashes($type)
    {
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertStringContainsString('shortfoo', $result, 'Alex\'s short commit');
        $this->assertStringContainsString('shortbar', $result, 'Ryan\'s short commit');
        $this->assertStringContainsString('shortbaz', $result, 'Leslie\'s short commit');
        $this->assertStringContainsString('shortdamn', $result, 'Charlie\'s short commit');
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogContainsAuthorNames($type)
    {
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertStringContainsString('Alex Atkins', $result);
        $this->assertStringContainsString('Ryan Reid', $result);
        $this->assertStringContainsString('Leslie Lolcopter', $result);
        $this->assertStringContainsString('Charlie Charizard', $result);
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogContainsCommitMessagesWithPrefixesStripped($type)
    {
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertStringContainsString('Changed foo for bar', $result);
        $this->assertStringNotContainsString('FIX Changed foo for bar', $result, 'Prefixes are stripped');
        $this->assertStringContainsString('Remove baz', $result);
        $this->assertStringNotContainsString('API Remove baz', $result, 'Prefixes are stripped');
        $this->assertStringContainsString('Added foobar', $result);
        $this->assertStringNotContainsString('NEW Added foobar', $result, 'Prefixes are stripped');
        $this->assertStringContainsString('Someone forgot the coffee!', $result);
        $this->assertStringNotContainsString(
            '[SS-2015-123] Someone forgot the coffee!',
            $result,
            'Prefixes are stripped'
        );
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogDoesNotContainUncategorisedCommitsByDefault($type)
    {
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertStringNotContainsString(
            'MNT Some maintenance commit',
            $result,
            'Maintenance are ignored by default'
        );
        $this->assertStringContainsString(
            'Some uncategorised commit',
            $result,
            'Uncategorised are included by default'
        );
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogContainsLinkToCommitInRepository($type)
    {
        $this->changelog->setIncludeOtherChanges(true);
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertStringContainsString('http://example.com/' . sha1('foo'), $result);
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogContainsUncategorisedCommitsIfRequested($type)
    {
        $this->changelog->setIncludeOtherChanges(true);
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertStringContainsString('Some uncategorised commit', $result);
        $this->assertStringContainsString('Val Vulcan', $result);
        $this->assertStringContainsString('2015-04-20', $result);
        $this->assertStringContainsString('http://example.com/' . sha1('damn'), $result);
        $this->assertStringContainsString('shortboo', $result, 'Val\'s short commit');
    }

    /**
     * See ChangelogItem::getDistinctDetails. This should prevent commits that are merged up from appearing
     * in changelogs more than once.
     *
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testDuplicateMergeUpCommitsAreExcluded($type)
    {
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertStringContainsString(sha1('damn'), $result, 'Latest commit is included');
        $this->assertStringNotContainsString(sha1('anotherdamn'), $result, 'Duplicated commit is ignored');
    }

    /**
     * @return array[]
     */
    public function changelogFormatProvider()
    {
        return [
            [Changelog::FORMAT_GROUPED],
            [Changelog::FORMAT_FLAT],
        ];
    }
}
