<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\RunTests;

/**
 * Run tests on this release
 *
 * @author dmooyman
 */
class Test extends Release
{
    protected $name = 'release:test';

    protected $description = 'Test this release';

    protected function fire()
    {
        // Get arguments
        $project = $this->getProject();

        // Steps
        $step = new RunTests($this, $project);
        $step->run($this->input, $this->output);
    }
}
