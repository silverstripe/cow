<?php

namespace SilverStripe\Cow\Commands\Release;

use Exception;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Steps\Release\PublishRelease;

/**
 * Top level publish command
 */
class Publish extends Release
{
    protected $name = 'release:publish';

    protected $description = 'Publish results of this release';

    protected function fire()
    {
        // Get arguments
        $project = $this->getProject();
        $releasePlan = $this->getReleasePlan();

        // Does bulk of module publishing, rewrite of dev branches, rewrite of tags, and actual tagging
        $publish = new PublishRelease($this, $project, $releasePlan);
        $publish->run($this->input, $this->output);
    }

    /**
     * @return LibraryRelease
     * @throws Exception
     */
    protected function getReleasePlan()
    {
        $plan = $this->getProject()->loadCachedPlan();
        if (empty($plan)) {
            throw new Exception("Please run 'cow release' before 'cow release:publish'");
        }
        return $plan;
    }
}
