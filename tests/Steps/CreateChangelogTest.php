<?php

namespace SilverStripe\Cow\Tests\Steps;

use PHPUnit\Framework\TestCase;
use SilverStripe\Cow\Application;
use SilverStripe\Cow\Commands\Release\Changelog;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Steps\Release\CreateChangelog;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\Output;

class CreateChangelogTest extends TestCase
{
    /**
     * @var Output
     */
    protected $output;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = new NullOutput();
    }

    public function testGetStepName()
    {
        $app = new Application();
        $step = new CreateChangelog(
            new Changelog($app, 'release:changelog'),
            new Project(''),
            null,
            $app->createTwigEnvironment()
        );

        $this->assertSame('changelog', $step->getStepName());
    }
}
