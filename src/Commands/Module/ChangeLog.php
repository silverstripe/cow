<?php

namespace SilverStripe\Cow\Commands\Module;

use SilverStripe\Cow\Steps\Module\ModuleChangeLog;

/**
 * Description of Create
 *
 * @author dmooyman
 */
class ChangeLog extends Module
{
    /**
     *
     * @var string
     */
    protected $name = 'module:changelog';
    
    protected $description = 'Generate changelog for a bunch of module';
    
    protected function fire()
    {
        // Get arguments
        $directory = $this->getInputDirectory();
        $modules = $this->getInputModules();
        $listIsExclusive = $this->getInputExclude();

        // Steps
        $step = new ModuleChangeLog($this, $directory, $modules, $listIsExclusive);
        $step->run($this->input, $this->output);
    }
}
