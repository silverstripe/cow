<?php

namespace SilverStripe\Cow\Steps\Release;

use SilverStripe\Cow\Model\Modules\Project;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run unit tests
 */
class RunTests extends ReleaseStep
{
    /**
     * @var Project
     */
    protected $project;

    public function getStepName()
    {
        return 'test';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        // Check tests exist
        $tests = $this->getProject()->getTests();
        if (empty($tests)) {
            $this->log(
                $output,
                "No tests configured for <info>" . $this->getProject()->getName() . '</info>, skipping'
            );
            return;
        }

        $this->log($output, "Running tests for <info>" . $this->getProject()->getName() . '</info>');
        $directory = $this->getProject()->getDirectory();
        foreach ($tests as $test) {
            $this->runCommand($output, "cd $directory && $test", "Tests failed!");
        }
    }
}
