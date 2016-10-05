<?php

namespace SilverStripe\Cow\Commands\Release;

use SilverStripe\Cow\Commands\Command;
use SilverStripe\Cow\Model\Modules\Project;
use SilverStripe\Cow\Steps\Release\CreateBranch;
use SilverStripe\Cow\Steps\Release\CreateBranches;
use SilverStripe\Cow\Steps\Release\PlanRelease;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Create a new branch
 *
 * @author dmooyman
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
        $branchAlias = new CreateBranches($this, $project, $releasePlan);
        $branchAlias->run($this->input, $this->output);
    }
}
