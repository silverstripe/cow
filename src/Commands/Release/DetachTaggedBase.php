<?php

namespace SilverStripe\Cow\Commands\Release;

use Exception;
use SilverStripe\Cow\Steps\Release\DetachTaggedBase as DetachTaggedBaseStep;

/**
 * release:detach-tagged-base command implementation
 *
 * Go through all the modules and checkout merge-base between
 * the current branch and the last released tag (stable or unstable).
 *
 * Simply speaking, this command checks out the last released commit within current branch,
 * avoiding anything that has been merged into the branch since the tag.
 * This may be helpful for releasing "audited" versions with some
 * cherry-picked patches on top of it.
 */
class DetachTaggedBase extends Release
{
    protected $name = 'release:detach-tagged-base';

    protected $description = 'Checkout and detach tagged commits for every module';

    /**
     * @throws Exception
     */
    protected function fire()
    {
        // Get arguments
        $version = $this->getInputVersion();
        $project = $this->getProject();

        $step = new DetachTaggedBaseStep($this, $project, $version, $this->progressBar);
        $step->run($this->input, $this->output);
    }
}
