<?php

namespace SilverStripe\Cow\Commands\Module;

use SilverStripe\Cow\Steps\Release\UpdateTranslations;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Description of Create
 *
 * @author dmooyman
 */
class Translate extends Module
{
    /**
     * @var string
     */
    protected $name = 'module:translate';

    protected $description = 'Translate your modules';
    
    protected function configureOptions()
    {
        parent::configureOptions();
        $this->addOption('push', 'p', InputOption::VALUE_NONE, "Push to git origin if successful");
    }

    protected function getInputPush()
    {
        return (bool)$this->input->getOption('push');
    }

    protected function fire()
    {
        $directory = $this->getInputDirectory();
        $modules = $this->getInputModules();
        $listIsExclusive = $this->getInputExclude();
        $push = $this->getInputPush();

        $translate = new UpdateTranslations($this, $directory, $modules, $listIsExclusive, $push);
        $translate->setVersionConstraint(null); // module:translate doesn't filter by self.version
        $translate->run($this->input, $this->output);
    }
}
