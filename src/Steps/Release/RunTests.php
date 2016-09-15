<?php

namespace SilverStripe\Cow\Steps\Release;

use Exception;
use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Steps\Step;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run unit tests
 */
class RunTests extends Step
{
    /**
     * @var Project
     */
    protected $project;

    /**
     * Create branch step
     *
     * @param Command $command
     * @param string $directory Where to translate
     * @param string|null $branch Branch name, if necessary
     */
    public function __construct(Command $command, $directory)
    {
        parent::__construct($command);

        $this->project = new Project($directory);
    }

    public function getStepName()
    {
        return 'test';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $directory = $this->project->getDirectory();
        $this->log($output, "Running unit tests in <info>{$directory}</info>");
        $this->runCommand($output, "cd $directory && vendor/bin/phpunit", "Tests failed!");
    }
}
