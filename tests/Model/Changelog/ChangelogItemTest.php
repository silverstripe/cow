<?php

namespace SilverStripe\Cow\Tests\Model\Changelog;

use Gitonomy\Git\Commit;
use PHPUnit\Framework\TestCase;
use SilverStripe\Cow\Model\Changelog\ChangelogItem;
use SilverStripe\Cow\Model\Changelog\ChangelogLibrary;

class ChangelogItemTest extends TestCase
{
    /**
     * @var ChangelogLibrary
     */
    protected $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->library = $this->getMockBuilder(ChangelogLibrary::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @param string $originalMessage
     * @param string $processedMessage
     * @param string $category
     * @dataProvider messageProvider
     */
    public function testGetType($originalMessage, $processedMessage, $category)
    {
        $commit = $this->createMock(Commit::class);
        $commit->expects($this->atLeastOnce())->method('getSubjectMessage')->willReturn($originalMessage);

        $item = new ChangelogItem($this->library, $commit);
        $this->assertSame($category, $item->getType());
    }

    /**
     * @param string $originalMessage
     * @param string $processedMessage
     * @dataProvider messageProvider
     */
    public function testGetShortMessage($originalMessage, $processedMessage)
    {
        $commit = $this->createMock(Commit::class);
        $commit->expects($this->once())->method('getSubjectMessage')->willReturn($originalMessage);

        $item = new ChangelogItem($this->library, $commit);
        $this->assertSame($processedMessage, $item->getShortMessage());
    }

    /**
     * @return array[] Original message, expected message, category
     */
    public function messageProvider()
    {
        return [
            ['DOC Add some documentation', 'Add some documentation', 'Documentation'],
            ['DOCS Some more docs', 'Some more docs', 'Documentation'],
            ['Merge PR', 'PR', 'Merge'],
            ['DEP Update lodash', 'Update lodash', 'Dependencies'],
            ['MNT Update composer.json', 'Update composer.json', 'Maintenance'],
            ['Update travis configs', 'Update travis configs', 'Maintenance'],
            ['BUG Fixed some regex rules', 'Fixed some regex rules', 'Bugfixes'],
            ['BUG FIX Fixed some regex rules', 'Fixed some regex rules', 'Bugfixes'],
            ['FIX Fixed some regex rules', 'Fixed some regex rules', 'Bugfixes'],
            ['FIX: Forgot colon', 'Forgot colon', 'Bugfixes'],
            ['Fixed some regex rules', 'Fixed some regex rules', 'Other changes'],
            ['Fixing some regex rules', 'Fixing some regex rules', 'Other changes'],
            ['Fixing Behat', 'Fixing Behat', 'Other changes'],
            ['Fix a typo in the docs', 'Fix a typo in the docs', 'Bugfixes'],
            ['Fixed a typo in the docs', 'Fixed a typo in the docs', 'Other changes'],
            ['FIXed a typo in the docs', 'FIXed a typo in the docs', 'Other changes'],
            ['FIX ed a typo in the docs', 'ed a typo in the docs', 'Bugfixes'],
            ['API Remove DataObject', 'Remove DataObject', 'API Changes'],
            ['API CHANGE Remove DataObject', 'CHANGE Remove DataObject', 'API Changes'],
            ['NEW Allow Python transpilation', 'Allow Python transpilation', 'Features and Enhancements'],
            ['ENH Support goland PHP extension', 'Support goland PHP extension', 'Features and Enhancements'],
            [
                '[SS-2047-123] Lower doubt with cow coverage',
                '[SS-2047-123] Lower doubt with cow coverage',
                'Security'
            ],
            [
                '[ss-2047-123] Lower doubt with cow coverage',
                '[ss-2047-123] Lower doubt with cow coverage',
                'Security'
            ],
            ['[SS-2047-123]: Logins now use passwords', '[SS-2047-123]: Logins now use passwords', 'Security'],
            ['[ss-2047-123]: Logins now use passwords', '[ss-2047-123]: Logins now use passwords', 'Security'],
            ['[CVE-1234-56789]: Fix something serious', 'Fix something serious', 'Security'],
            ['[CVE-1234-12345] Remove admin login backdoor', 'Remove admin login backdoor', 'Security'],
            ['[cve-1234-123456] added admin login backdoor', 'added admin login backdoor', 'Security'],
            ['[cve-2018-8001] testing is cool', 'testing is cool', 'Security'],
            [
                'Default fallback doesn\'t categorise commit',
                'Default fallback doesn\'t categorise commit',
                'Other changes'
            ],
            // __CLASS__ and __TRAIT__ will be treated as markdown
            ['DOC Lorem __CLASS__ ipsum', '`Lorem __CLASS__ ipsum`', 'Documentation'],
            ['DOC Lorem `__TRAIT__` ipsum', '`Lorem __TRAIT__ ipsum`', 'Documentation'],
            // Markdownlint MD037 - Spaces inside emphasis markers
            // Only a single underscore will not escape
            [
                'ENH Use a _config.php file only',
                'Use a _config.php file only',
                'Features and Enhancements'
            ],
            // Two underscores will escape
            [
                'ENH Use a _config.php file or _config directory',
                '`Use a _config.php file or _config directory`',
                'Features and Enhancements'
            ],
            // Three underscores will also escape
            [
                'ENH Use a _config.php file or _config directory or _config.yml',
                '`Use a _config.php file or _config directory or _config.yml`',
                'Features and Enhancements'
            ],
        ];
    }

    /**
     * @param string $message
     * @param bool $expected
     * @dataProvider ignoredMessageProvider
     */
    public function testIsIgnored($message, $expected)
    {
        $commit = $this->createMock(Commit::class);
        $commit->expects($this->once())->method('getSubjectMessage')->willReturn($message);

        $item = new ChangelogItem($this->library, $commit);
        $this->assertSame($expected, $item->isIgnored());
    }

    /**
     * @return array[]
     */
    public function ignoredMessageProvider()
    {
        return [
            ['Merge branch 1 into 2', false],
            ['Merge remote tracking branch origin/foo into master', false],
            ['Update branch alias', false],
            ['Remove obsolete branch alias', false],
            ['Added 1.2.3-rc1 changelog', false],
            ['Blocked revisions blah', false],
            ['Initialized merge tracking against x', false],
            ['Created branches 1.2 and 1.3', false],
            ['Created tags 1.2.3 and 1.3.0', false],
            ['NOTFORMERGE Whoops, we merged it anyway', false],
            ['', false],
            ['Fix a bug somewhere', false],
            ['API Changing something big', false],
            ['NEW Enhancing something', false],
            ['[SS-2047-123] Something serious', false],
            ['[CVE-2019-12345] Fixed something', false],
        ];
    }
}
