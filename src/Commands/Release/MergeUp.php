<?php

namespace SilverStripe\Cow\Commands\Release;

use Exception;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Steps\Release\MergeUpRelease;
use Symfony\Component\Console\Input\InputOption;

/**
 * Merge up released commits to the major branch and optionally remove x.y branch.
 * E.g. if you just released 4.12.0, this will merge 4.12 up to 4.
 */
class MergeUp extends Release
{
    protected $name = 'release:mergeup';

    protected $description = 'Merge up released commits to the major branch';

    protected function fire()
    {
        $project = $this->getProject();
        $releasePlan = $this->getReleasePlan();
        $merge = new MergeUpRelease($this, $project, $releasePlan);
        $merge->run($this->input, $this->output);
    }

    /**
     * @return LibraryRelease
     * @throws Exception
     */
    protected function getReleasePlan()
    {
        $plan = $this->getProject()->loadCachedPlan();
        if (empty($plan)) {
            throw new Exception("Please run 'cow release' before 'cow release:mergeup'");
        }
        return $plan;
    }

    protected function configureOptions()
    {
        parent::configureOptions();
        $this->addOption(
            'no-push',
            null,
            InputOption::VALUE_NONE,
            'Avoids pushing branches up to the remote'
        );
    }
}
