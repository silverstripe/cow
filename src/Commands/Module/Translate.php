<?php

namespace SilverStripe\Cow\Commands\Module;

use SilverStripe\Cow\Steps\Release\UpdateTranslations;

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

    protected function fire()
    {
        $directory = $this->getInputDirectory();
        $modules = $this->getInputModules();
        $listIsExclusive = $this->getInputExclude();
        $push = $this->getInputPush();

        $translate = new UpdateTranslations($this, $directory, $modules, $listIsExclusive, $push);
        $translate->run($this->input, $this->output);
    }
}
