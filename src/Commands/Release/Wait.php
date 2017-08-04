<?php

namespace SilverStripe\Cow\Commands\Release;

use Exception;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Steps\Release\Wait as WaitStep;

/**
 * Top level publish command
 */
class Wait extends Release
{
    protected $name = 'release:wait';

    protected $description = 'Wait for this release to be available';

    protected function fire()
    {
        // Get arguments
        $project = $this->getProject();
        $releasePlan = $this->getReleasePlan();

        // Once pushed, wait until installable
        $wait = new WaitStep($this, $project, $releasePlan);
        $wait->run($this->input, $this->output);
    }

    /**
     * @return LibraryRelease
     * @throws Exception
     */
    protected function getReleasePlan()
    {
        $plan = $this->getProject()->loadCachedPlan();
        if (empty($plan)) {
            throw new Exception("Please run 'cow release' before 'cow release:wait'");
        }
        return $plan;
    }
}
