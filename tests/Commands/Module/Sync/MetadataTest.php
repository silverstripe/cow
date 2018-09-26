<?php

namespace SilverStripe\Cow\Tests\Commands\Module\Sync;

use PHPUnit_Framework_TestCase;
use SilverStripe\Cow\Commands\Module\Sync\Metadata;
use SilverStripe\Cow\Utility\SupportedModuleLoader;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class MetadataTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var SupportedModuleLoader
     */
    protected $moduleLoader;

    /**
     * @var Metadata
     */
    protected $metadata;

    protected function setUp()
    {
        parent::setUp();

        $this->moduleLoader = $this->getMockBuilder(SupportedModuleLoader::class)
            ->setMethods(['getModules', 'getRemoteData'])
            ->getMock();

        $this->moduleLoader->expects($this->once())->method('getModules')->willReturn([
            'silverstripe/foo',
            'someoneelse/foo',
        ]);

        $this->moduleLoader->expects($this->once())
            ->method('getRemoteData')
            ->with('templates/LICENSE.md')
            ->willReturn('foo');

        $this->metadata = $this->getMockBuilder(Metadata::class)
            ->setMethods([
                'syncRepositories',
                'writeDataToFile',
                'stageFile',
                'hasChanges',
                'commitChanges',
                'pushChanges',
            ])
            ->setConstructorArgs([$this->moduleLoader])
            ->getMock();
    }

    public function testSyncRepositoriesByDefault()
    {
        $this->metadata->expects($this->once())->method('syncRepositories');

        $output = $this->executeCommand([], ['yes']);
        $this->assertContains('Done', $output);
    }

    public function testDoesNotSyncRepositoriesWithSkipUpdateOption()
    {
        $this->metadata->expects($this->never())->method('syncRepositories');

        $output = $this->executeCommand(['--skip-update' => true], ['yes']);
        $this->assertContains('Done', $output);
    }

    public function testSkippingFiles()
    {
        $output = $this->executeCommand([], ['no']);
        $this->assertContains('Skipping LICENSE.md', $output);
        $this->assertContains('Done', $output);
    }

    public function testSkipThirdPartyRepositories()
    {
        $this->metadata->expects($this->once())->method('writeDataToFile');

        $output = $this->executeCommand([], ['yes']);
        $this->assertContains('Done', $output);
    }

    public function testApplyChanges()
    {
        $this->metadata->expects($this->once())->method('writeDataToFile')->with($this->anything(), 'foo');
        $this->metadata->expects($this->once())->method('stageFile');
        $this->metadata->expects($this->once())->method('hasChanges')->willReturn(true);
        $this->metadata->expects($this->once())->method('commitChanges');
        $this->metadata->expects($this->once())->method('pushChanges');

        $output = $this->executeCommand([], ['yes']);
        $this->assertContains('Done', $output);
    }

    public function testCommitAndPushIsSkippedWithoutChanges()
    {
        $this->metadata->expects($this->once())->method('writeDataToFile')->with($this->anything(), 'foo');
        $this->metadata->expects($this->once())->method('stageFile');
        $this->metadata->expects($this->once())->method('hasChanges')->willReturn(false);
        $this->metadata->expects($this->never())->method('commitChanges');
        $this->metadata->expects($this->never())->method('pushChanges');

        $output = $this->executeCommand([], ['yes']);
        $this->assertContains('Done', $output);
    }

    /**
     * Wrapper for executing a command and returning its output
     *
     * @param array $extraArgs
     * @param array $inputs
     * @return string
     */
    protected function executeCommand(array $extraArgs = [], array $inputs = [])
    {
        $application = new Application();
        $application->add($this->metadata);

        $command = $application->find('module:sync:metadata');
        $commandTester = new CommandTester($command);

        if (!empty($inputs)) {
            $commandTester->setInputs($inputs);
        }

        $commandTester->execute(array_merge(['command' => $command->getName()], $extraArgs));
        return $commandTester->getDisplay();
    }
}
