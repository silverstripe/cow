<?php

namespace SilverStripe\Cow\Steps\Branch;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Module;
use SilverStripe\Cow\Steps\Release\ModuleStep;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Checks out a specific branch on a module, or list of modules.
 * Note: Unloke "merge", this command will simply warn of missing branches
 * rather than erroring out
 */
class CheckoutBranch extends ModuleStep
{
    /**
     * Branch
     *
     * @var string
     */
    protected $branch = null;

    /**
     * Remote name
     *
     * @var string
     */
    protected $remote = null;

    public function __construct(Command $command, $directory, $modules, $listIsExclusive, $branch, $remote)
    {
        parent::__construct($command, $directory, $modules, $listIsExclusive);
        $this->setBranch($branch);
        $this->setRemote($remote);
    }

    public function getStepName()
    {
        return 'checkout';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log($output, "Checkout out <info>" . $this->getBranch() . "</info>");
        foreach ($this->getModules() as $module) {
            $this->checkoutModule($input, $output, $module);
        }
        $this->log($output, "All modules were checked out");
    }

    /**
     * Checks out the branch on the given module
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param Module $module
     * @throws \Exception
     */
    protected function checkoutModule(InputInterface $input, OutputInterface $output, Module $module)
    {
        $this->log($output, "Checking out module <info>" . $module->getName() . "</info>");

        $module->fetch($output, $this->getRemote());

        try {
            $module->checkout($output, $this->getBranch(), $this->getRemote());
        } catch (\Exception $ex) {
            // Sometimes modules don't exist in either source or destination branch;
            // Treat this as a warning rather than an error.
            $this->log($output, "Skipping module with error: <error>" . $ex->getMessage() . "</error>");
            return;
        }
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

    /**
     * @param string $remote
     * @return $this
     */
    public function setRemote($remote)
    {
        $this->remote = $remote;
        return $this;
    }

    /**
     * @return string
     */
    public function getRemote()
    {
        return $this->remote ?: 'origin';
    }
}
