<?php

namespace SilverStripe\Cow\Commands\Branch;

use SilverStripe\Cow\Commands\Module\Module;
use SilverStripe\Cow\Steps\Branch\CheckoutBranch;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Checkout a branch on a list of modules
 *
 * @author dmooyman
 */
class Checkout extends Module
{
    /**
     * @var string
     */
    protected $name = 'branch:checkout';

    protected $description = 'Checkout a branch on a list of modules';

    protected function configureOptions()
    {
        $this->addArgument('branch', InputArgument::REQUIRED, 'Branch name to checkout');
        $this->addOption('remote', 'r', InputOption::VALUE_REQUIRED, 'Remote name to use');
        parent::configureOptions();
    }

    protected function fire()
    {
        $directory = $this->getInputDirectory();
        $modules = $this->getInputModules();
        $listIsExclusive = $this->getInputExclude();
        $branch = $this->getInputBranch();
        $remote = $this->getInputRemote();

        $merge = new CheckoutBranch($this, $directory, $modules, $listIsExclusive, $branch, $remote);
        $merge->run($this->input, $this->output);
    }



    /**
     * Get branch to merge from
     *
     * @return string
     */
    protected function getInputBranch()
    {
        return $this->input->getArgument('branch');
    }

    /**
     * Get remote name
     *
     * @return string
     */
    protected function getInputRemote()
    {
        return $this->input->getOption('remote');
    }
}
