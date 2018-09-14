<?php

namespace SilverStripe\Cow\Tests\Model;

use DateTime;
use Gitonomy\Git\Commit;
use Gitonomy\Git\Log;
use Gitonomy\Git\Repository;
use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Model\Changelog\Changelog;
use SilverStripe\Cow\Model\Changelog\ChangelogLibrary;
use SilverStripe\Cow\Model\Modules\Library;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Model\Release\Version;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class ChangelogTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Changelog
     */
    protected $changelog;

    protected function setUp()
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
            new Commit($repository, sha1('damn'), [
                'shortHash' => 'shortdamn',
                'subjectMessage' => '[SS-2015-123] Someone forgot the coffee!',
                'authorName' => 'Charlie Charizard',
                'authorDate' => new DateTime('2015-04-20 04:20:00'),
            ]),
            new Commit($repository, sha1('anotherdamn'), [
                'subjectMessage' => '[SS-2015-123] Someone forgot the coffee!',
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

        $version = new Version('1.0.0');
        $priorVersion = new Version('0.5.0');

        $release = new LibraryRelease($library, $version);

        $changelogLibrary = new ChangelogLibrary($release, $priorVersion);

        $this->changelog = new Changelog($changelogLibrary);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Unknown changelog format foobar
     */
    public function testGetMarkdownThrowsExceptionOnInvalidType()
    {
        $this->changelog->getMarkdown($this->output, 'foobar');
    }

    public function testGetMarkdownGroupedContainsHeadings()
    {
        $result = $this->changelog->getMarkdown($this->output, Changelog::FORMAT_GROUPED);

        $this->assertContains('## Change Log', $result);
        $this->assertContains('### Security', $result);
        $this->assertContains('### Bugfixes', $result);
        $this->assertContains('### Features and Enhancements', $result);
        $this->assertContains('### API Changes', $result);
    }

    public function testGetMarkdownFlatDoesNotContainHeadings()
    {
        $result = $this->changelog->getMarkdown($this->output, Changelog::FORMAT_FLAT);

        $this->assertNotContains('## Change Log', $result);
        $this->assertNotContains('### Bugfixes', $result);
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogContainsCommitDates($type)
    {
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertContains('2014-12-25', $result, 'Alex\'s commit date');
        $this->assertContains('1990-05-02', $result, 'Ryan\'s commit date');
        $this->assertContains('2015-01-03', $result, 'Leslie\'s commit date');
        $this->assertContains('2015-04-20', $result, 'Charlie\'s commit date');
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

        $this->assertContains('shortfoo', $result, 'Alex\'s short commit');
        $this->assertContains('shortbar', $result, 'Ryan\'s short commit');
        $this->assertContains('shortbaz', $result, 'Leslie\'s short commit');
        $this->assertContains('shortdamn', $result, 'Charlie\'s short commit');
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogContainsAuthorNames($type)
    {
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertContains('Alex Atkins', $result);
        $this->assertContains('Ryan Reid', $result);
        $this->assertContains('Leslie Lolcopter', $result);
        $this->assertContains('Charlie Charizard', $result);
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogContainsCommitMessagesWithPrefixesStripped($type)
    {
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertContains('Changed foo for bar', $result);
        $this->assertNotContains('FIX Changed foo for bar', $result, 'Prefixes are stripped');
        $this->assertContains('Remove baz', $result);
        $this->assertNotContains('API Remove baz', $result, 'Prefixes are stripped');
        $this->assertContains('Added foobar', $result);
        $this->assertNotContains('NEW Added foobar', $result, 'Prefixes are stripped');
        $this->assertContains('Someone forgot the coffee!', $result);
        $this->assertNotContains('[SS-2015-123] Someone forgot the coffee!', $result, 'Prefixes are stripped');
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogDoesNotContainUncategorisedCommitsByDefault($type)
    {
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertNotContains('Some uncategorised commit', $result, 'Uncategorised are ignored by default');
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogContainsLinkToCommitInRepository($type)
    {
        $this->changelog->setIncludeOtherChanges(true);
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertContains('http://example.com/' . sha1('foo'), $result);
    }

    /**
     * @param string $type
     * @dataProvider changelogFormatProvider
     */
    public function testChangelogContainsUncategorisedCommitsIfRequested($type)
    {
        $this->changelog->setIncludeOtherChanges(true);
        $result = $this->changelog->getMarkdown($this->output, $type);

        $this->assertContains('Some uncategorised commit', $result);
        $this->assertContains('Val Vulcan', $result);
        $this->assertContains('2015-04-20', $result);
        $this->assertContains('http://example.com/' . sha1('damn'), $result);
        $this->assertContains('shortboo', $result, 'Val\'s short commit');
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

        $this->assertContains(sha1('damn'), $result, 'Latest commit is included');
        $this->assertNotContains(sha1('anotherdamn'), $result, 'Duplicated commit is ignored');
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
