<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\UpdateTranslations;

/**
 * Description of Create
 *
 * @author dmooyman
 */
class Translate extends Release
{
    protected $name = 'release:translate';

    protected $description = 'Translate this release';

    protected function fire()
    {
        // Get arguments
        $directory = $this->getInputDirectory();
        $modules = $this->getReleaseModules();

        // Steps
        $step = new UpdateTranslations($this, $directory, $modules);
        $step->run($this->input, $this->output);
    }
}
