<?php

namespace SilverStripe\Cow\Tests\Model\Changelog;

use Gitonomy\Git\Commit;
use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Model\Changelog\ChangelogItem;
use SilverStripe\Cow\Model\Changelog\ChangelogLibrary;

class ChangelogItemTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var ChangelogLibrary
     */
    protected $library;

    protected function setUp()
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
            ['BUG Fixed some regex rules', 'Fixed some regex rules', 'Bugfixes'],
            ['BUG FIX Fixed some regex rules', 'Fixed some regex rules', 'Bugfixes'],
            ['FIX Fixed some regex rules', 'Fixed some regex rules', 'Bugfixes'],
            ['FIX: Forgot colon', 'Forgot colon', 'Bugfixes'],
            ['Fixed some regex rules', 'Fixed some regex rules', 'Bugfixes'],
            ['Fixing some regex rules', 'Fixing some regex rules', 'Bugfixes'],
            ['Fixing Behat', 'Fixing Behat', 'Bugfixes'],
            ['Fix a typo in the docs', 'Fix a typo in the docs', 'Bugfixes'],
            ['Fixed a typo in the docs', 'Fixed a typo in the docs', 'Bugfixes'],
            ['FIXed a typo in the docs', 'FIXed a typo in the docs', 'Bugfixes'],
            ['FIX ed a typo in the docs', 'ed a typo in the docs', 'Bugfixes'],
            ['API Remove DataObject', 'Remove DataObject', 'API Changes'],
            ['API CHANGE Remove DataObject', 'Remove DataObject', 'API Changes'],
            ['NEW Allow Python transpilation', 'Allow Python transpilation', 'Features and Enhancements'],
            ['ENH Support goland PHP extension', 'Support goland PHP extension', 'Features and Enhancements'],
            ['[SS-2047-123] Lower doubt with cow coverage', 'Lower doubt with cow coverage', 'Security'],
            ['[ss-2047-123] Lower doubt with cow coverage', 'Lower doubt with cow coverage', 'Security'],
            ['[SS-2047-123]: Logins now use passwords', 'Logins now use passwords', 'Security'],
            ['[SS-2047-123]: Logins now use passwords', 'Logins now use passwords', 'Security'],
            ['[CVE-1234-56789]: Fix something serious', 'Fix something serious', 'Security'],
            ['[CVE-1234-12345] Remove admin login backdoor', 'Remove admin login backdoor', 'Security'],
            ['[cve-1234-12345] added admin login backdoor', 'added admin login backdoor', 'Security'],
            ['[cve-2018-8001] testing is cool', 'testing is cool', 'Security'],
            ['Default fallback doesn\'t categorise commit', 'Default fallback doesn\'t categorise commit', null],
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
            ['Merge branch 1 into 2', true],
            ['Merge remote tracking branch origin/foo into master', true],
            ['Update branch alias', true],
            ['Remove obsolete branch alias', true],
            ['Added 1.2.3-rc1 changelog', true],
            ['Blocked revisions blah', true],
            ['Initialized merge tracking against x', true],
            ['Created branches 1.2 and 1.3', true],
            ['Created tags 1.2.3 and 1.3.0', true],
            ['NOTFORMERGE Whoops, we merged it anyway', true],
            ['', true],
            ['Fix a bug somewhere', false],
            ['API Changing something big', false],
            ['NEW Enhancing something', false],
            ['[SS-2047-123] Something serious', false],
            ['[CVE-2019-12345] Fixed something', false],
        ];
    }
}
