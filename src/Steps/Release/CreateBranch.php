<?php

namespace SilverStripe\Cow\Steps\Release;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Branch all releasable libraries to a branch
 */
class CreateBranch extends ReleaseStep
{
    /**
     * Branch name
     *
     * @var string
     */
    protected $branch;

    /**
     * @return string
     */
    public function getBranch()
    {
        return $this->branch;
    }

    /**
     * @param string $branch
     * @return $this
     */
    public function setBranch($branch)
    {
        $this->branch = $branch;
        return $this;
    }

    /**
     * CreateBranch constructor.
     * @param Command $command
     * @param Project $project
     * @param string $branch
     */
    public function __construct(Command $command, Project $project, $branch)
    {
        parent::__construct($command, $project);
        $this->setBranch($branch);
    }

    public function getStepName()
    {
        return 'branch';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        // Branch all modules
        $branch = $this->getBranch();
        $this->log($output, "Branching all modules to <info>{$branch}</info>");
        foreach ($this->getProject()->getAllChildren() as $library) {
            // Skip upgrade-only libraries
            if ($library->isUpgradeOnly()) {
                continue;
            }
            // Skip libraries already on that branch
            $thisBranch = $library->getBranch();
            if ($thisBranch === $branch) {
                continue;
            }
            $this->log(
                $output,
                "Branching module ".$library->getName()." from <info>{$thisBranch}</info> to <info>{$branch}</info>"
            );
            $library->checkout($output, $branch, 'origin', true);
        }
        $this->log($output, 'Branching complete');
    }
}
