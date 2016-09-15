<?php

namespace SilverStripe\Cow\Steps\Release;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Branch each module to a new temp branch (unless it's already on that branch)
 */
class CreateBranch extends ModuleStep
{
    /**
     * Branch name
     *
     * @var string|null
     */
    protected $branch;

    /**
     * Create branch step
     *
     * @param Command $command
     * @param Project $project
     * @param string|null $branch Branch name, if necessary
     * @param array $modules Optional list of modules to limit to
     * @param bool $listIsExclusive If this list is exclusive. If false, this is inclusive
     */
    public function __construct(Command $command, $project, $branch, $modules = array(), $listIsExclusive = false)
    {
        parent::__construct($command, $project, $modules, $listIsExclusive);
        $this->branch = $branch;
    }

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

    public function getStepName()
    {
        return 'branch';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $branch = $this->getBranch();
        if (empty($branch)) {
            $this->log($output, "Skipping branch step");
            return;
        }

        $this->log($output, "Branching all modules to <info>{$branch}</info>");
        foreach ($this->getModules() as $module) {
            $thisBranch = $module->getBranch();
            if ($thisBranch != $branch) {
                $this->log(
                    $output,
                    "Branching module ".$module->getInstalledName()." from <info>{$thisBranch}</info> to <info>{$branch}</info>"
                );
                $module->checkout($output, $branch, 'origin', true);
            }
        }
        $this->log($output, 'Branching complete');
    }
}
