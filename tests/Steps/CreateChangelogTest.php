<?php

namespace SilverStripe\Cow\Tests\Steps;

use SilverStripe\Cow\Commands\Release\Changelog;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Steps\Release\CreateChangelog;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\Output;

class CreateChangelogTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Output
     */
    protected $output;

    protected function setUp()
    {
        parent::setUp();

        $this->output = new NullOutput();
    }

    public function testGetStepName()
    {
        $step = new CreateChangelog(new Changelog('release:changelog'), new Project(''));

        $this->assertSame('changelog', $step->getStepName());
    }

    public function testCommitChanges()
    {
        $this->markTestSkipped('Not yet implemented');
    }

    public function testRun()
    {
        $this->markTestSkipped('Not yet implemented');
    }
}
