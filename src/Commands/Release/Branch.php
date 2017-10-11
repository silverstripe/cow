<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Steps\Release\PlanRelease;
use SilverStripe\Cow\Steps\Release\RewriteReleaseBranches;

/**
 * Create branches for this release
 */
class Branch extends Release
{
    /**
     * @var string
     */
    protected $name = 'release:branch';

    protected $description = 'Branch all modules';

    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $project = $this->getProject();

        // Build and confirm release plan
        $buildPlan = new PlanRelease($this, $project, $version);
        $buildPlan->run($this->input, $this->output);
        $releasePlan = $buildPlan->getReleasePlan();

        // Branch all modules properly
        $branchAlias = new RewriteReleaseBranches($this, $project, $releasePlan);
        $branchAlias->run($this->input, $this->output);
    }
}
