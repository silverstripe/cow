<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\PlanRelease;

/**
 * Builds a release plan
 */
class Plan extends Release
{
    protected $name = 'release:plan';

    protected $description = 'Plan release dependencies';

    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $project = $this->getProject();
        $branching = $this->getBranching();

        // Build and confirm release plan
        $buildPlan = new PlanRelease($this, $project, $version, $branching, $this->progressBar);
        $buildPlan->run($this->input, $this->output);
    }
}
