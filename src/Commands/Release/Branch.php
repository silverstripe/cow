<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Steps\Release\CreateBranch;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Create a new branch
 *
 * @author dmooyman
 */
class Branch extends Command
{
    /**
     *
     * @var string
     */
    protected $name = 'release:branch';

    protected $description = 'Branch all modules';

    protected function configureOptions()
    {
        $this
            ->addArgument('branch', InputArgument::REQUIRED, 'Branch each module to this')
            ->addOption('directory', 'd', InputOption::VALUE_REQUIRED, 'Optional directory to release project from');
    }

    protected function fire()
    {
        // Get arguments
        $branch = $this->getInputBranch();
        $directory = $this->getInputDirectory();
        $project = new Project($directory);

        // Steps
        $step = new CreateBranch($this, $project, $branch);
        $step->run($this->input, $this->output);
    }

    /**
     * Determine the branch name that should be used
     *
     * @return string|null
     */
    protected function getInputBranch()
    {
        return $this->input->getArgument('branch');
    }

    /**
     * Get the directory the project is, or will be in
     *
     * @return string
     */
    protected function getInputDirectory()
    {
        return $this->input->getOption('directory') ?: getcwd();
    }
}
