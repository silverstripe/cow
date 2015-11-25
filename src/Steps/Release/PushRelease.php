<?php

namespace SilverStripe\Cow\Steps\Release;

use SilverStripe\Cow\Steps\ModuleStep;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Push up changes to github for each module (including tags)
 *
 * @author dmooyman
 */
class PushRelease extends ModuleStep
{
    public function getStepName()
    {
        return 'push';
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->log($output, "Pushing all modules to origin");
        $modules = $this->getModules();
        foreach ($modules as $module) {
            $this->log($output, "Pushing module <info>" . $module->getName() . "</info>");
            $module->pushTo('origin', true);
        }
        $this->log($output, 'Pushing complete');
    }
}
