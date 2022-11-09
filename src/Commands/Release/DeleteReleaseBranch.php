<?php

namespace SilverStripe\Cow\Commands\Release;

use Exception;
use SilverStripe\Cow\Model\Release\LibraryRelease;
use SilverStripe\Cow\Steps\Release\DeleteReleaseBranchStep;

/**
 * Delete the x.y-release branches
 */
class DeleteReleaseBranch extends Release
{
    protected $name = 'release:deletereleasebranch';

    protected $description = 'Deletes the release branches from the remote';

    protected function fire()
    {
        $project = $this->getProject();
        $releasePlan = $this->getReleasePlan();
        $merge = new DeleteReleaseBranchStep($this, $project, $releasePlan);
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
            throw new Exception("Please run 'cow release' before 'cow release:deletereleasebranch'");
        }
        return $plan;
    }
}
